import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { setActivePinia, createPinia } from "pinia";
import Checkout from "./Checkout.vue";
import { nextTick } from "vue";
import { useCheckout } from "../../js/useCheckout.ts";
import { reload, __reset, __setSection, __setStale } from "../../../../../Test/Js/stubs/customerData.ts";

// Live watchEffects on the stub's shared ref make an unmounted component react
// to a later test's __setSection.
const mounted: { unmount: () => void }[] = [];

const settle = async (): Promise<void> => {
    await Promise.resolve();
    await nextTick();
    await nextTick();
};

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

    it("keeps a finished step marked and reachable after the shopper steps back", async () => {
        const wrapper = render();
        const checkout = useCheckout();
        checkout.goToStep("shipping");
        checkout.goToStep("payment");
        await nextTick();
        checkout.goToStep("shipping");
        await nextTick();

        const items = wrapper.findAll(".step-rail__item");
        expect(items[1].attributes("data-state")).toBe("active");
        expect(items[2].attributes("data-state")).toBe("done");
        expect(items[2].find("button").attributes("disabled")).toBeUndefined();
        expect(items[3].attributes("data-state")).toBe("pending");
    });


    it("keeps the total in front of the shopper on a phone, where the summary is below the fold", () => {
        const wrapper = render();
        const bar = wrapper.find("[data-total-bar]");

        expect(bar.exists()).toBe(true);
        expect(bar.classes()).toContain("checkout-total-bar");
        expect(bar.text()).toContain("$73.00");
    });

    it("drops the total bar once the bag is empty", async () => {
        const wrapper = render({ ...CONFIG, quote: { items: [], subtotal: "", grandTotal: "" } });
        await nextTick();

        expect(wrapper.find("[data-total-bar]").exists()).toBe(false);
    });

    it("prefers the recalculated grand total over the primed one", async () => {
        const wrapper = render();
        useCheckout().totalSegments = [{ code: "grand_total", title: "Grand Total", value: 91.5 }];
        await nextTick();

        expect(wrapper.find("[data-total-bar]").text()).toContain("91.50");
    });

    it("pins the order summary beside a long form", () => {
        expect(render().find("aside").classes()).toContain("lg:sticky");
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
        await settle();

        expect(useCheckout().ready).toBe(true);
        expect(wrapper.text()).toContain("Crown Summit Backpack");
        expect(wrapper.text()).toContain("$38.00");
    });

    it("stays unready while the section is missing", async () => {
        render(PUBLIC_ONLY);
        await nextTick();

        expect(useCheckout().ready).toBe(false);
    });

    // Measured: this reconcile serialises with the batch hydrate on the session lock.
    it("does not reconcile the cart section when the quote comes from the section", () => {
        __setSection("obsidian-checkout", PRIVATE_SECTION);
        render(PUBLIC_ONLY);

        expect(reload.calls).not.toContainEqual([["cart"]]);
    });

    it("still reconciles the cart section when the page inlined the quote", () => {
        render({ ...PUBLIC_ONLY, ...PRIVATE_SECTION });

        expect(reload.calls).toContainEqual([["cart"]]);
    });

    // The engine's batch hydrate waits for browser idle; the cart cannot.
    it("requests its own section immediately when it is not in the store yet", () => {
        render(PUBLIC_ONLY);

        expect(reload.calls).toContainEqual([["obsidian-checkout"]]);
    });

    it("refetches the section even when it already has one", () => {
        __setSection("obsidian-checkout", PRIVATE_SECTION);
        render(PUBLIC_ONLY);

        expect(reload.calls).toContainEqual([["obsidian-checkout"]]);
    });

    it("acts on nothing until that fetch comes back", () => {
        __setSection("obsidian-checkout", PRIVATE_SECTION);
        render(PUBLIC_ONLY);

        expect(useCheckout().ready).toBe(false);
    });

    it("applies a section that arrives after mount", async () => {
        const wrapper = render(PUBLIC_ONLY);
        await settle();

        __setSection("obsidian-checkout", PRIVATE_SECTION);
        await nextTick();

        expect(useCheckout().ready).toBe(true);
        expect(wrapper.text()).toContain("Crown Summit Backpack");
    });
});

describe("Checkout.vue — configurable and custom options in the summary", () => {
    const WITH_OPTIONS = {
        ...CONFIG,
        quote: {
            items: [
                {
                    id: 7,
                    name: "Chaz Kangeroo Hoodie",
                    qty: 1,
                    rowTotal: "$59.00",
                    image: "",
                    options: [
                        { label: "Color", value: "Black" },
                        { label: "Size", value: "XS" },
                        { label: "Monogram", value: "JMJ" },
                    ],
                },
            ],
            subtotal: "$59.00",
            grandTotal: "$59.00",
        },
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

    it("lists each selected option under its line", () => {
        const wrapper = render(WITH_OPTIONS);
        const options = wrapper.findAll("[data-item-options] li");

        expect(options).toHaveLength(3);
        expect(options[0].text()).toBe("Color: Black");
        expect(options[2].text()).toBe("Monogram: JMJ");
    });

    it("draws nothing for a simple product", () => {
        const wrapper = render(CONFIG);

        expect(wrapper.find("[data-item-options]").exists()).toBe(false);
    });
});

// Magento rotates `private_content_version` on POST only; a currency switch is a GET.
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
        await settle();

        __setSection("obsidian-checkout", FRESH);
        await nextTick();

        expect(useCheckout().ready).toBe(true);
        expect(wrapper.text()).toContain("Crown Summit Backpack");
    });

    it("revalidates at most once, so a wrong shell cannot spin", async () => {
        __setSection("obsidian-checkout", STALE);
        render(PUBLIC_ONLY);
        await settle();

        __setSection("obsidian-checkout", { ...STALE, quote: { ...STALE.quote, subtotal: "$39.00" } });
        await nextTick();

        expect(reload.calls.filter((c) => c[0]?.[0] === "obsidian-checkout")).toHaveLength(2);
        expect(useCheckout().ready).toBe(true);
    });

    it("does not revalidate a section without a stamp", async () => {
        __setSection("obsidian-checkout", { ...STALE, context: undefined });
        render(PUBLIC_ONLY);
        await settle();

        expect(reload.calls.filter((c) => c[0]?.[0] === "obsidian-checkout")).toHaveLength(1);
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
        await settle();

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
        await settle();

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

// Adding to the cart reloads `cart,messages` only, so the stored checkout section
// keeps the pre-add empty quote while the store has not synced the new version.
// Trusting it sends a shopper who just added an item back to the bag page.
describe("Checkout.vue — an unsynced snapshot cannot decide the cart is empty", () => {
    const PUBLIC_ONLY = { layoutMode: "stepped", maxSummaryItems: 10, baseUrl: "https://shop.test/" };
    const EMPTY_SECTION = {
        isLoggedIn: false, customerEmail: "", maskedCartId: "m", currencyFormat: "$%s",
        quote: { items: [], subtotal: "$0.00", grandTotal: "$0.00" }, vault: [],
    };
    const FILLED_SECTION = {
        ...EMPTY_SECTION,
        quote: {
            items: [{ id: 1, name: "Joust Duffle Bag", qty: 1, rowTotal: "$34.00" }],
            subtotal: "$34.00", grandTotal: "$34.00",
        },
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

    function render() {
        return track(mount(Checkout, {
            props: { config: PUBLIC_ONLY, labels: {} },
            global: { plugins: [pinia] },
        }));
    }

    it("does not redirect on an empty quote the store has not synced", async () => {
        __setStale(true);
        __setSection("obsidian-checkout", EMPTY_SECTION);
        render();
        await nextTick();

        expect(window.location.assign).not.toHaveBeenCalled();
    });

    it("stays unready rather than trusting the unsynced copy", async () => {
        __setStale(true);
        __setSection("obsidian-checkout", EMPTY_SECTION);
        render();
        await nextTick();

        expect(useCheckout().ready).toBe(false);
    });

    // The store's own hydrate already has a full reload in flight, and a partial
    // one would not stamp the version marker anyway — so the snapshot would stay
    // unsynced forever and the page would never become ready.
    it("does not chase the staleness with a second partial reload", async () => {
        __setStale(true);
        __setSection("obsidian-checkout", EMPTY_SECTION);
        render();
        await settle();

        expect(reload.calls.filter((c) => c[0]?.[0] === "obsidian-checkout")).toHaveLength(1);
    });

    it("paints the cart once the synced section arrives", async () => {
        __setStale(true);
        __setSection("obsidian-checkout", EMPTY_SECTION);
        const wrapper = render();
        await nextTick();

        __setStale(false);
        __setSection("obsidian-checkout", FILLED_SECTION);
        await nextTick();

        expect(window.location.assign).not.toHaveBeenCalled();
        expect(useCheckout().ready).toBe(true);
        expect(wrapper.text()).toContain("Joust Duffle Bag");
    });

    it("still redirects once a synced section confirms the cart is empty", async () => {
        __setStale(true);
        __setSection("obsidian-checkout", EMPTY_SECTION);
        render();
        await nextTick();

        __setStale(false);
        __setSection("obsidian-checkout", { ...EMPTY_SECTION });
        await nextTick();

        expect(window.location.assign).toHaveBeenCalledWith("https://shop.test/checkout/cart/");
    });
});

