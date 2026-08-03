import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { setActivePinia, createPinia } from "pinia";
import Checkout from "./Checkout.vue";
import { nextTick } from "vue";
import { useCheckout } from "../../js/useCheckout.ts";
import { reload, __reset, __setSection } from "../../../../../Test/Js/stubs/customerData.ts";

// Every mount here leaves live watchEffects on the section stub's shared ref, so
// a component from an earlier test reacts to a later test's __setSection and
// writes into whatever store is active by then. Unmounting is what keeps each
// test's assertions about its own component.
const mounted: { unmount: () => void }[] = [];

function track<T extends { unmount: () => void }>(wrapper: T): T {
    mounted.push(wrapper);

    return wrapper;
}

afterEach(() => {
    mounted.splice(0).forEach((wrapper) => wrapper.unmount());
});

const CONFIG = {
    isLoggedIn: false,
    customerEmail: "",
    quote: {
        items: [{ id: 1, name: "Joust Duffle Bag", qty: 2, rowTotal: "$68.00", image: "" }],
        subtotal: "$68.00",
        grandTotal: "$73.00",
    },
};

describe("Checkout.vue", () => {
    let pinia;

    beforeEach(() => {
        pinia = createPinia();
        setActivePinia(pinia);
        __reset();
    });

    function render(config = CONFIG, labels = {}) {
        return track(mount(Checkout, {
            props: { config, labels },
            global: { plugins: [pinia] },
        }));
    }

    it("renders the four-step rail with the current step marked", () => {
        const wrapper = render();
        const steps = wrapper.findAll("ol li");
        expect(steps).toHaveLength(4);

        const current = wrapper.findAll('[aria-current="step"]');
        expect(current).toHaveLength(1);
        expect(current[0].text()).toContain("Identification");
    });

    it("paints the server-primed order summary without any fetch", () => {
        const wrapper = render();
        expect(wrapper.text()).toContain("Joust Duffle Bag");
        expect(wrapper.text()).toContain("× 2");
        expect(wrapper.text()).toContain("$68.00");
        expect(wrapper.text()).toContain("$73.00");
    });

    it("starts a logged-in customer on the shipping step", () => {
        const wrapper = render({ ...CONFIG, isLoggedIn: true, customerEmail: "ada@shop.test" });
        const current = wrapper.find('[aria-current="step"]');
        expect(current.text()).toContain("Shipping");
    });

    it("uses provided i18n labels", () => {
        const wrapper = render(CONFIG, { stepShipping: "Envío", summary: "Resumen" });
        expect(wrapper.text()).toContain("Envío");
        expect(wrapper.text()).toContain("Resumen");
    });

    it("reconciles the cart section from the authoritative quote on mount", () => {
        render();
        expect(reload.calls).toContainEqual([["cart"]]);
    });
});

describe("Checkout.vue — cacheable shell", () => {
    // What a cached page inlines: store-scoped keys only, no quote, no identity.
    const PUBLIC_ONLY = { layoutMode: "stepped", maxSummaryItems: 10 };
    const PRIVATE_SECTION = {
        isLoggedIn: false,
        customerEmail: "",
        maskedCartId: "mask42",
        currencyFormat: "$%s",
        quote: {
            items: [{ id: 9, name: "Crown Summit Backpack", qty: 1, rowTotal: "$38.00", image: "" }],
            subtotal: "$38.00",
            grandTotal: "$38.00",
        },
        vault: [],
    };

    let pinia;

    beforeEach(() => {
        pinia = createPinia();
        setActivePinia(pinia);
        __reset();
    });

    function render(config) {
        return track(mount(Checkout, { props: { config, labels: {} }, global: { plugins: [pinia] } }));
    }

    it("takes the private half from the obsidian-checkout section", async () => {
        __setSection("obsidian-checkout", PRIVATE_SECTION);
        const wrapper = render(PUBLIC_ONLY);
        await nextTick();

        expect(useCheckout().ready).toBe(true);
        expect(wrapper.text()).toContain("Crown Summit Backpack");
        expect(wrapper.text()).toContain("$38.00");
    });

    // Never paint a half-known checkout: an empty summary and a real cart look
    // identical to a shopper, and the second one loses the order.
    it("stays unready while the section is missing", async () => {
        render(PUBLIC_ONLY);
        await nextTick();

        expect(useCheckout().ready).toBe(false);
    });

    // Measured in the browser on a cold localStorage: this reconcile fired first
    // (sections=cart, 111ms) and pushed the store's own batch hydrate — the call
    // that actually carries the quote — to 135ms, where it took 113ms instead of
    // ~68 because the two serialise on the PHP session lock. Items appeared at
    // 258ms instead of 102ms. On this path the batch already refetches `cart`.
    it("does not reconcile the cart section when the quote comes from the section", () => {
        __setSection("obsidian-checkout", PRIVATE_SECTION);
        render(PUBLIC_ONLY);

        expect(reload.calls).not.toContainEqual([["cart"]]);
    });

    it("still reconciles the cart section when the page inlined the quote", () => {
        render({ ...PUBLIC_ONLY, ...PRIVATE_SECTION });

        expect(reload.calls).toContainEqual([["cart"]]);
    });

    // The engine defers its batch hydrate to requestIdleCallback, which is right
    // for a header badge and wrong for the content this page exists to show.
    // Measured cold: the batch started at 151ms and the cart appeared at 250ms
    // against 185ms on the uncached page. So the checkout asks for its own
    // section straight away and lets the batch follow.
    it("requests its own section immediately when it is not in the store yet", () => {
        render(PUBLIC_ONLY);

        expect(reload.calls).toContainEqual([["obsidian-checkout"]]);
    });

    it("does not refetch a section it already has", () => {
        __setSection("obsidian-checkout", PRIVATE_SECTION);
        render(PUBLIC_ONLY);

        expect(reload.calls).not.toContainEqual([["obsidian-checkout"]]);
    });

    it("applies a section that arrives after mount", async () => {
        const wrapper = render(PUBLIC_ONLY);
        await nextTick();

        __setSection("obsidian-checkout", PRIVATE_SECTION);
        await nextTick();

        expect(useCheckout().ready).toBe(true);
        expect(wrapper.text()).toContain("Crown Summit Backpack");
    });
});

// Magento rotates `private_content_version` on POST only, and switching currency
// or store view is a GET. Nothing else ages this section out, so without the
// stamp the summary keeps showing the previous currency — reproduced in the
// browser: the server answered EUR 53.10 while the page showed USD 59.00.
describe("Checkout.vue — a section from another currency or store", () => {
    const PUBLIC_ONLY = {
        layoutMode: "stepped",
        maxSummaryItems: 10,
        storeCode: "default",
        currencyCode: "EUR",
    };
    const STALE = {
        isLoggedIn: false,
        customerEmail: "",
        maskedCartId: "mask42",
        currencyFormat: "$%s",
        quote: {
            items: [{ id: 9, name: "Crown Summit Backpack", qty: 1, rowTotal: "$38.00", image: "" }],
            subtotal: "$38.00",
            grandTotal: "$38.00",
        },
        vault: [],
        context: { storeCode: "default", currencyCode: "USD" },
    };
    const FRESH = { ...STALE, context: { storeCode: "default", currencyCode: "EUR" } };

    let pinia;

    beforeEach(() => {
        pinia = createPinia();
        setActivePinia(pinia);
        __reset();
    });

    function render(config) {
        return track(mount(Checkout, { props: { config, labels: {} }, global: { plugins: [pinia] } }));
    }

    it("refetches instead of painting a stale currency", async () => {
        __setSection("obsidian-checkout", STALE);
        render(PUBLIC_ONLY);
        await nextTick();

        expect(reload.calls).toContainEqual([["obsidian-checkout"]]);
        expect(useCheckout().ready).toBe(false);
    });

    it("applies the section once it comes back in the page's currency", async () => {
        __setSection("obsidian-checkout", STALE);
        const wrapper = render(PUBLIC_ONLY);
        await nextTick();

        __setSection("obsidian-checkout", FRESH);
        await nextTick();

        expect(useCheckout().ready).toBe(true);
        expect(wrapper.text()).toContain("Crown Summit Backpack");
    });

    // The shell is what carries the page's currency, and the plan does not let
    // correctness rest on the vary being right. If a mismatch survives the
    // refetch, refetching again forever would be worse than showing the section.
    it("refetches at most once, so a wrong shell cannot spin", async () => {
        __setSection("obsidian-checkout", STALE);
        render(PUBLIC_ONLY);
        await nextTick();

        __setSection("obsidian-checkout", { ...STALE, quote: { ...STALE.quote, subtotal: "$39.00" } });
        await nextTick();

        expect(reload.calls.filter((c) => c[0]?.[0] === "obsidian-checkout")).toHaveLength(1);
        expect(useCheckout().ready).toBe(true);
    });

    it("leaves a section without a stamp alone", async () => {
        __setSection("obsidian-checkout", { ...STALE, context: undefined });
        render(PUBLIC_ONLY);
        await nextTick();

        expect(reload.calls).not.toContainEqual([["obsidian-checkout"]]);
        expect(useCheckout().ready).toBe(true);
    });

    it("catches a store switch as well as a currency switch", async () => {
        __setSection("obsidian-checkout", { ...FRESH, context: { storeCode: "es", currencyCode: "EUR" } });
        render(PUBLIC_ONLY);
        await nextTick();

        expect(reload.calls).toContainEqual([["obsidian-checkout"]]);
        expect(useCheckout().ready).toBe(false);
    });
});

describe("Checkout.vue — all-or-nothing while the private half is missing", () => {
    const PUBLIC_ONLY = { layoutMode: "stepped", maxSummaryItems: 10 };
    let pinia;

    beforeEach(() => {
        pinia = createPinia();
        setActivePinia(pinia);
        __reset();
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    function render(config, search = "") {
        vi.stubGlobal("location", {
            search,
            href: `https://shop.test/checkout/${search}`,
            assign: vi.fn(),
        });
        return track(mount(Checkout, { props: { config, labels: {} }, global: { plugins: [pinia] } }));
    }

    // An unready checkout has itemCount 0, which is indistinguishable from a real
    // empty cart. Telling a shopper their bag is empty when it is not is the worst
    // failure this page has.
    it("never claims the bag is empty before the quote is known", async () => {
        const wrapper = render(PUBLIC_ONLY);
        await nextTick();

        expect(useCheckout().ready).toBe(false);
        expect(wrapper.text()).not.toContain("Your bag is empty.");
    });

    it("falls back to the uncached page when the section never arrives", async () => {
        render(PUBLIC_ONLY);
        await nextTick();

        vi.advanceTimersByTime(10000);

        expect(window.location.assign).toHaveBeenCalledTimes(1);
        expect(window.location.assign.mock.calls[0][0]).toContain("obsidian_shell=0");
    });

    it("does not fall back twice — the bypass page must not bounce", async () => {
        render(PUBLIC_ONLY, "?obsidian_shell=0");
        await nextTick();

        vi.advanceTimersByTime(10000);

        expect(window.location.assign).not.toHaveBeenCalled();
    });

    it("does not fall back once the section has landed", async () => {
        // A cart with items: an empty one legitimately navigates to the bag page,
        // which would muddy what this test is actually about.
        __setSection("obsidian-checkout", {
            isLoggedIn: false, customerEmail: "", maskedCartId: "m", currencyFormat: "$%s",
            quote: {
                items: [{ id: 1, name: "X", qty: 1, rowTotal: "$1.00" }],
                subtotal: "$1.00", grandTotal: "$1.00",
            },
            vault: [],
        });
        render(PUBLIC_ONLY);
        await nextTick();

        vi.advanceTimersByTime(10000);

        expect(window.location.assign).not.toHaveBeenCalled();
    });
});

describe("Checkout.vue — empty cart on a cached shell", () => {
    const PUBLIC_ONLY = { layoutMode: "stepped", maxSummaryItems: 10, baseUrl: "https://shop.test/" };
    const EMPTY_SECTION = {
        isLoggedIn: false, customerEmail: "", maskedCartId: "m", currencyFormat: "$%s",
        quote: { items: [], subtotal: "$0.00", grandTotal: "$0.00" }, vault: [],
    };

    let pinia;

    beforeEach(() => {
        pinia = createPinia();
        setActivePinia(pinia);
        __reset();
        vi.stubGlobal("location", { search: "", href: "https://shop.test/checkout/", assign: vi.fn() });
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    // Magento redirects an empty cart to the bag page from the controller. A
    // cached shell is shared, so that guard cannot run server-side any more —
    // measured: with the shell on, an empty-cart visitor gets 200 instead of 302.
    it("sends an empty cart to the bag page, restoring the native redirect", async () => {
        __setSection("obsidian-checkout", EMPTY_SECTION);
        mount(Checkout, { props: { config: PUBLIC_ONLY, labels: {} }, global: { plugins: [pinia] } });
        await nextTick();

        expect(window.location.assign).toHaveBeenCalledWith("https://shop.test/checkout/cart/");
    });

    it("does not redirect while the quote is still unknown", async () => {
        mount(Checkout, { props: { config: PUBLIC_ONLY, labels: {} }, global: { plugins: [pinia] } });
        await nextTick();

        expect(window.location.assign).not.toHaveBeenCalled();
    });

    it("does not redirect a cart that has items", async () => {
        __setSection("obsidian-checkout", {
            ...EMPTY_SECTION,
            quote: { items: [{ id: 1, name: "X", qty: 1, rowTotal: "$1.00" }], subtotal: "$1.00", grandTotal: "$1.00" },
        });
        mount(Checkout, { props: { config: PUBLIC_ONLY, labels: {} }, global: { plugins: [pinia] } });
        await nextTick();

        expect(window.location.assign).not.toHaveBeenCalled();
    });
});

