import { describe, it, expect, beforeEach, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { setActivePinia, createPinia } from "pinia";
import ShippingStep from "./ShippingStep.vue";
import { useCheckout } from "MageObsidian_Checkout::js/useCheckout";

const CONFIG = {
    isLoggedIn: false,
    restBaseUrl: "https://shop.test/rest/default/V1/",
    maskedCartId: "mask42",
    currencyFormat: "$%s",
    defaultCountry: "US",
    quote: { items: [], subtotal: "", grandTotal: "" },
};

const DIRECTORY = {
    countries: [{ value: "US", label: "United States" }],
    regions: { US: [{ id: 12, code: "CA", name: "California" }] },
    statesRequired: ["US"],
    displayAllRegions: false,
    defaultCountry: "US",
};

const FLATRATE = { carrier_code: "flatrate", method_code: "flatrate", carrier_title: "Flat Rate", method_title: "Fixed", amount: 5, available: true };

function mockFetch(response: unknown, ok = true, status = 200) {
    globalThis.fetch = vi.fn().mockResolvedValue({ ok, status, json: () => Promise.resolve(response) });
}

const flush = () => new Promise((r) => setTimeout(r));

describe("ShippingStep", () => {
    let pinia;

    beforeEach(() => {
        pinia = createPinia();
        setActivePinia(pinia);
        vi.restoreAllMocks();
        useCheckout().init(CONFIG);
    });

    function render() {
        return mount(ShippingStep, {
            props: { directory: DIRECTORY },
            global: { plugins: [pinia] },
        });
    }

    it("estimates rates and lists them with formatted prices", async () => {
        mockFetch([FLATRATE]);
        const wrapper = render();

        await wrapper.find("button").trigger("click");
        await flush();
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain("Flat Rate");
        expect(wrapper.text()).toContain("$5.00");
        expect(wrapper.find('[role="radiogroup"]').exists()).toBe(true);
    });

    it("advances to payment after saving the chosen method", async () => {
        mockFetch([FLATRATE]);
        const wrapper = render();
        await wrapper.find("button").trigger("click");
        await flush();
        await wrapper.vm.$nextTick();

        mockFetch({ payment_methods: [{ code: "checkmo", title: "Check" }], totals: { grand_total: 39 } });
        const buttons = wrapper.findAll("button");
        await buttons[buttons.length - 1].trigger("click");
        await flush();

        expect(useCheckout().step).toBe("payment");
        expect(useCheckout().paymentMethods).toEqual([{ code: "checkmo", title: "Check" }]);
    });

    it("shows free for a zero-amount rate", async () => {
        mockFetch([{ ...FLATRATE, carrier_title: "Free Shipping", amount: 0 }]);
        const wrapper = render();
        await wrapper.find("button").trigger("click");
        await flush();
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain("Free");
    });
});

describe("ShippingStep — saved address picker", () => {
    const AUSTIN = {
        id: 20,
        label: "Ada Lovelace, 12 Baker Street, Austin, Texas 78701",
        isDefaultShipping: true,
        firstname: "Ada", lastname: "Lovelace", company: "Analytical Engines",
        street: ["12 Baker Street", "Flat 3"], city: "Austin",
        region: "Texas", regionId: 57, postcode: "78701",
        countryId: "US", telephone: "+1 512 555 0142",
    };
    const MIAMI = {
        ...AUSTIN, id: 21, isDefaultShipping: false,
        label: "Ada Lovelace, 440 Ocean Drive, Miami, Florida 33139",
        street: ["440 Ocean Drive"], city: "Miami", region: "Florida", regionId: 18,
    };

    let pinia;

    beforeEach(() => {
        pinia = createPinia();
        setActivePinia(pinia);
        vi.restoreAllMocks();
    });

    function render(addresses) {
        const checkout = useCheckout();
        checkout.initPublic({ ...CONFIG, layoutMode: "stepped" });
        checkout.applyPrivate({ ...CONFIG, isLoggedIn: true, customerEmail: "ada@shop.test", addresses });

        return mount(ShippingStep, { props: { directory: DIRECTORY }, global: { plugins: [pinia] } });
    }

    function picker(wrapper) {
        return wrapper.find('[data-saved-addresses] select');
    }

    it("lists every saved address plus a new-address option", () => {
        const wrapper = render([AUSTIN, MIAMI]);
        const options = picker(wrapper).findAll("option");

        expect(options).toHaveLength(3);
        expect(options[0].text()).toContain("Austin");
        expect(options[1].text()).toContain("Miami");
        expect(options[2].text()).toContain("New address");
    });

    it("starts on the default shipping address", () => {
        const wrapper = render([MIAMI, AUSTIN]);

        expect(picker(wrapper).element.value).toBe("20");
    });

    it("refills the form when another address is picked", async () => {
        const wrapper = render([AUSTIN, MIAMI]);
        await picker(wrapper).setValue("21");

        expect(useCheckout().shippingAddress.city).toBe("Miami");
    });

    it("empties the form for a new address", async () => {
        const wrapper = render([AUSTIN, MIAMI]);
        await picker(wrapper).setValue("");

        expect(useCheckout().shippingAddress.city).toBe("");
        expect(useCheckout().selectedAddressId).toBeNull();
    });

    // A guest, and a customer whose address book is empty, must see the plain form.
    it("is absent when there is nothing saved", () => {
        const wrapper = render([]);

        expect(wrapper.find("[data-saved-addresses]").exists()).toBe(false);
    });
});
