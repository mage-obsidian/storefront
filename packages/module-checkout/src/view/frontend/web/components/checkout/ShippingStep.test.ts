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

    it("gives the continue button the full width of a phone once rates are listed", async () => {
        mockFetch([FLATRATE]);
        const wrapper = render();

        await wrapper.find("button").trigger("click");
        await flush();
        await wrapper.vm.$nextTick();

        const button = wrapper.find(".checkout-cta");
        expect(button.exists()).toBe(true);
        expect(button.classes()).toContain("btn--block");
        expect(button.classes()).toContain("lg:w-fit");
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

    function cards(wrapper) {
        return wrapper.findAll('[data-saved-addresses] .field-radio-card');
    }

    function pick(wrapper, index) {
        return cards(wrapper)[index].find("input").trigger("change");
    }

    it("lists every saved address plus a new-address option", () => {
        const options = cards(render([AUSTIN, MIAMI]));

        expect(options).toHaveLength(3);
        expect(options[0].text()).toContain("Austin");
        expect(options[1].text()).toContain("Miami");
        expect(options[2].text()).toContain("new address");
    });

    it("offers the saved addresses as a radio group, not a dropdown", () => {
        const wrapper = render([AUSTIN, MIAMI]);

        expect(wrapper.find("[data-saved-addresses]").attributes("role")).toBe("radiogroup");
        expect(wrapper.find("[data-saved-addresses] select").exists()).toBe(false);
    });

    it("starts on the default shipping address", () => {
        const wrapper = render([MIAMI, AUSTIN]);

        expect(cards(wrapper)[1].find("input").element.checked).toBe(true);
        expect(useCheckout().selectedAddressId).toBe(20);
    });

    it("refills the form when another address is picked", async () => {
        const wrapper = render([AUSTIN, MIAMI]);
        await pick(wrapper, 1);

        expect(useCheckout().shippingAddress.city).toBe("Miami");
    });

    it("empties the form for a new address", async () => {
        const wrapper = render([AUSTIN, MIAMI]);
        await pick(wrapper, 2);

        expect(useCheckout().shippingAddress.city).toBe("");
        expect(useCheckout().selectedAddressId).toBeNull();
    });

    it("folds the address fields away while a saved address is in use", async () => {
        const wrapper = render([AUSTIN, MIAMI]);
        expect(wrapper.find("[data-address-fields]").exists()).toBe(false);

        await pick(wrapper, 2);

        expect(wrapper.find("[data-address-fields]").exists()).toBe(true);
    });

    it("offers to file a new address, but never one already in the book", async () => {
        const wrapper = render([AUSTIN, MIAMI]);
        await pick(wrapper, 2);
        expect(wrapper.find("[data-save-address]").exists()).toBe(true);

        await pick(wrapper, 0);
        expect(wrapper.find("[data-save-address]").exists()).toBe(false);
    });

    it("is absent when there is nothing saved", () => {
        const wrapper = render([]);

        expect(wrapper.find("[data-saved-addresses]").exists()).toBe(false);
    });

    it("keeps the shipping method section on screen and says what is missing", () => {
        const wrapper = render();
        const status = wrapper.find("[data-rates-status]");

        expect(wrapper.find("#shipping-methods-heading").exists()).toBe(true);
        expect(status.attributes("aria-live")).toBe("polite");
        expect(status.text()).toContain("Complete your address");
    });

    it("shows a spinner in the status while the rates are in flight", async () => {
        globalThis.fetch = vi.fn().mockReturnValue(new Promise(() => {}));
        const wrapper = render();

        await wrapper.find("button").trigger("click");
        await flush();
        await wrapper.vm.$nextTick();

        const status = wrapper.find("[data-rates-status]");
        expect(status.find(".btn__spinner").exists()).toBe(true);
        expect(status.text()).toContain("Looking for shipping options");
    });

    it("says so when the address returns no options at all", async () => {
        mockFetch([]);
        const wrapper = render();

        await wrapper.find("button").trigger("click");
        await flush();
        await wrapper.vm.$nextTick();

        expect(wrapper.find("[data-rates-status]").text()).toContain("No shipping options");
        expect(wrapper.find('[role="radiogroup"]').exists()).toBe(false);
    });

    it("announces the option count to assistive tech once they land", async () => {
        mockFetch([FLATRATE]);
        const wrapper = render();

        await wrapper.find("button").trigger("click");
        await flush();
        await wrapper.vm.$nextTick();

        expect(wrapper.find("[data-rates-status] .sr-only").text()).toContain("1 shipping options");
    });

    it("hides the advance button until there is something to advance with", async () => {
        const wrapper = render();
        expect(wrapper.findAll("button")).toHaveLength(1);

        mockFetch([FLATRATE]);
        await wrapper.find("button").trigger("click");
        await flush();
        await wrapper.vm.$nextTick();

        expect(wrapper.findAll("button")).toHaveLength(2);
    });
});

describe("ShippingStep — sending the shopper to a field the checkout is waiting on", () => {
    const AUSTIN = {
        id: 20,
        label: "Ada Lovelace, 12 Baker Street, Austin, Texas 78701",
        isDefaultShipping: true,
        firstname: "Ada", lastname: "Lovelace", company: "",
        street: ["12 Baker Street"], city: "Austin",
        region: "Texas", regionId: 57, postcode: "78701",
        countryId: "US", telephone: "+1 512 555 0142",
    };

    let pinia;

    beforeEach(() => {
        pinia = createPinia();
        setActivePinia(pinia);
        vi.restoreAllMocks();
    });

    function render(addresses = []) {
        const checkout = useCheckout();
        checkout.initPublic({ ...CONFIG, layoutMode: "onepage" });
        checkout.applyPrivate({ ...CONFIG, isLoggedIn: addresses.length > 0, customerEmail: "", addresses });
        const wrapper = mount(ShippingStep, {
            props: { directory: DIRECTORY, hideAdvance: true },
            global: { plugins: [pinia] },
        });

        return { wrapper, checkout, form: () => wrapper.find("[data-address-form-stub]") };
    }

    it("marks the field and hands the focus to the address form", async () => {
        const { wrapper, form } = render();

        wrapper.vm.focusMissingField("firstname");
        await wrapper.vm.$nextTick();

        expect(form().attributes("data-invalid-fields")).toBe("firstname");
        expect(wrapper.findComponent({ ref: "addressForm" }).vm.focused).toContain("firstname");
    });

    it("drops the mark by itself once the field stops being missing", async () => {
        const { wrapper, checkout, form } = render();

        wrapper.vm.focusMissingField("firstname");
        await wrapper.vm.$nextTick();
        expect(form().attributes("data-invalid-fields")).toBe("firstname");

        checkout.shippingAddress.firstname = "Grace";
        await wrapper.vm.$nextTick();

        expect(form().attributes("data-invalid-fields")).toBe("");
    });

    it("stays put when a saved address is in charge and there is no form to focus", async () => {
        const { wrapper } = render([AUSTIN]);
        expect(wrapper.find("[data-address-form-stub]").exists()).toBe(false);

        expect(() => wrapper.vm.focusMissingField("firstname")).not.toThrow();
    });
});
