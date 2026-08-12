import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";
import { setActivePinia, createPinia } from "pinia";
import OnePageCheckout from "./OnePageCheckout.vue";
import { useCheckout } from "../../js/useCheckout.ts";
import { __reset } from "../../../../../Test/Js/stubs/customerData.ts";

const CONFIG = {
    isLoggedIn: false,
    customerEmail: "",
    restBaseUrl: "https://shop.test/rest/default/V1/",
    maskedCartId: "mask42",
    defaultCountry: "US",
    currencyFormat: "$%s",
    layoutMode: "onepage",
    quote: { items: [{ id: 1, name: "Joust Duffle Bag", qty: 1, rowTotal: "$34.00" }], subtotal: "$34.00", grandTotal: "$34.00" },
};

const DIRECTORY = { countries: [], regions: {}, statesRequired: [], displayAllRegions: false, defaultCountry: "US" };

const COMPLETE_ADDRESS = {
    firstname: "Ada", lastname: "Lovelace", company: "", street: ["1 Rue", ""],
    city: "Paris", region: "", regionId: null, postcode: "75001", countryId: "FR", telephone: "0102030405",
};

const FLATRATE = { carrier_code: "flatrate", method_code: "flatrate", carrier_title: "Flat Rate", available: true };

const SAVED_ADDRESS = {
    id: 7, label: "Ada, 1 Rue, Paris", isDefaultShipping: true,
    ...COMPLETE_ADDRESS,
};

function render(configOverrides = {}, before?: (checkout: ReturnType<typeof useCheckout>) => void) {
    const pinia = createPinia();
    setActivePinia(pinia);
    const checkout = useCheckout();
    checkout.init({ ...CONFIG, ...configOverrides });
    before?.(checkout);
    const wrapper = mount(OnePageCheckout, {
        props: { directory: DIRECTORY, labels: {} },
        global: { plugins: [pinia] },
    });
    return { wrapper, checkout };
}

describe("OnePageCheckout.vue", () => {
    beforeEach(() => {
        __reset();
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    it("consolidates into two stages: Information (contact + shipping) and Payment", () => {
        const { wrapper } = render();
        expect(wrapper.find("#onepage-information-heading").exists()).toBe(true);
        // Contact (email) lives inside the Information stage for guests.
        expect(wrapper.find("#checkout-email").exists()).toBe(true);
        // Payment stage stays hidden until shipping-information is saved.
        expect(wrapper.find("#onepage-payment-heading").exists()).toBe(false);
    });

    it("drops the contact block for a logged-in customer but keeps the Information stage", () => {
        const { wrapper } = render({ isLoggedIn: true, customerEmail: "ada@shop.test" });
        expect(wrapper.find("#onepage-information-heading").exists()).toBe(true);
        expect(wrapper.find("#checkout-email").exists()).toBe(false);
    });

    it("reveals the Payment stage once shipping-information returns methods", async () => {
        const { wrapper, checkout } = render();
        checkout.paymentMethods = [{ code: "checkmo", title: "Check / Money order" }];
        await nextTick();
        expect(wrapper.find("#onepage-payment-heading").exists()).toBe(true);
    });

    it("renders a two-stage progress stepper; Payment is not reachable before shipping is saved", () => {
        const { wrapper } = render();
        const buttons = wrapper.findAll("nav ol li button");
        expect(buttons).toHaveLength(2);
        expect(wrapper.find('nav [aria-current="step"]').text()).toContain("Information");
        expect(buttons[1].attributes("disabled")).toBeDefined();
    });

    it("moves the active stage to Payment once shipping is saved", async () => {
        const { wrapper, checkout } = render();
        expect(wrapper.find('nav [aria-current="step"]').text()).toContain("Information");

        checkout.paymentMethods = [{ code: "checkmo", title: "Check" }];
        await nextTick();
        expect(wrapper.find('nav [aria-current="step"]').text()).toContain("Payment");
    });

    it("keeps two stages for a logged-in customer", () => {
        const { wrapper } = render({ isLoggedIn: true, customerEmail: "ada@shop.test" });
        expect(wrapper.findAll("nav ol li button")).toHaveLength(2);
    });

    it("asks the store to bring the quote in line whenever the shipping input changes", async () => {
        const { checkout } = render();
        const sync = vi.spyOn(checkout, "scheduleShippingSync");

        checkout.shippingAddress = { ...COMPLETE_ADDRESS };
        await nextTick();
        expect(sync).toHaveBeenCalledTimes(1);

        checkout.selectMethod(FLATRATE);
        await nextTick();
        expect(sync).toHaveBeenCalledTimes(2);

        await nextTick();
        expect(sync).toHaveBeenCalledTimes(2);
    });

    it("asks once at mount, so an address the store already held still gets its rates", () => {
        let sync: ReturnType<typeof vi.spyOn> | null = null;
        const { checkout } = render(
            { isLoggedIn: true, customerEmail: "ada@shop.test", addresses: [SAVED_ADDRESS] },
            (store) => {
                sync = vi.spyOn(store, "scheduleShippingSync");
            },
        );

        expect(checkout.shippingAddress.postcode).toBe("75001");
        expect(sync).toHaveBeenCalledTimes(1);
    });

    it("also reacts to the guest email, which the address signature cannot carry", async () => {
        const { checkout } = render();
        const sync = vi.spyOn(checkout, "scheduleShippingSync");

        checkout.email = "guest@shop.test";
        await nextTick();

        expect(sync).toHaveBeenCalledTimes(1);
    });

    it("drops the pending sync when the checkout goes away", () => {
        const { wrapper, checkout } = render();
        const cancel = vi.spyOn(checkout, "cancelShippingSync");

        wrapper.unmount();

        expect(cancel).toHaveBeenCalled();
    });

    it("scrolls back to a finished stage through the component's own section, not a lookup by id", async () => {
        const { wrapper, checkout } = render();
        checkout.paymentMethods = [{ code: "checkmo", title: "Check" }];
        await nextTick();

        const section = wrapper.find("#onepage-information").element as HTMLElement;
        const scrollIntoView = vi.fn();
        section.scrollIntoView = scrollIntoView;
        vi.spyOn(document, "getElementById");

        await wrapper.findAll(".step-rail__button")[0].trigger("click");

        expect(scrollIntoView).toHaveBeenCalledWith({ behavior: "smooth", block: "start" });
        expect(document.getElementById).not.toHaveBeenCalled();
    });

    it("keeps the shopper out of a stage that is not reachable yet", async () => {
        const { wrapper } = render();

        await wrapper.findAll(".step-rail__button")[1].trigger("click");

        expect(wrapper.find("#onepage-payment").exists()).toBe(false);
    });

});

describe("OnePageCheckout — why the payment section is not there yet", () => {
    const QUOTABLE_ONLY = {
        firstname: "", lastname: "", company: "129467797", street: ["test", ""],
        city: "GENERAL LAGOS", region: "", regionId: null, postcode: "123",
        countryId: "US", telephone: "2115654",
    };

    beforeEach(() => {
        __reset();
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    const CHECKMO = { code: "checkmo", title: "Check / Money order" };

    // estimate-shipping-methods answers with the rates; shipping-information with
    // the payment methods. One blanket mock cannot stand in for both.
    function mockRates(rates: unknown[]) {
        globalThis.fetch = vi.fn().mockImplementation((url: string) => {
            const body = String(url).includes("shipping-information")
                ? { payment_methods: [CHECKMO], totals: { grand_total: 39 } }
                : rates;
            return Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve(body) });
        });
    }

    const settle = async () => {
        await vi.advanceTimersByTimeAsync(1000);
        await nextTick();
    };

    async function stalled({ address = QUOTABLE_ONLY, rates = [FLATRATE], pickMethod = true, ...config } = {}) {
        mockRates(rates);
        const { wrapper, checkout } = render(config, (store) => {
            Object.assign(store.shippingAddress, address);
        });
        await settle();
        if (pickMethod && checkout.shippingMethods.length > 0) {
            checkout.selectMethod(FLATRATE);
            await settle();
        }
        return { wrapper, checkout };
    }

    it("names every field still standing between the shopper and the payment methods", async () => {
        const { wrapper } = await stalled({ isLoggedIn: true, customerEmail: "ada@shop.test" });

        const notice = wrapper.find("[data-payment-blockers]");
        expect(notice.exists()).toBe(true);
        expect(notice.text()).toContain("First name");
        expect(notice.text()).toContain("Last name");
        expect(notice.text()).not.toContain("City");
        expect(wrapper.find("#onepage-payment-pending-heading").text()).toContain("Payment");
    });

    it("says nothing while there are no shipping options — the shipping section already explains that", async () => {
        const { wrapper, checkout } = await stalled({ rates: [], isLoggedIn: true, customerEmail: "ada@shop.test" });

        expect(checkout.shippingMethods).toEqual([]);
        expect(wrapper.find("#onepage-payment-pending").exists()).toBe(false);
    });

    it("waits for the request in flight instead of accusing the shopper mid-keystroke", async () => {
        const { wrapper, checkout } = await stalled({ isLoggedIn: true, customerEmail: "ada@shop.test" });
        expect(wrapper.find("[data-payment-blockers]").exists()).toBe(true);

        checkout.savingShipping = true;
        await nextTick();

        expect(wrapper.find("[data-payment-blockers]").exists()).toBe(false);
        expect(wrapper.find("#onepage-payment-pending").text()).toContain("Confirming your shipping choice");
    });

    it("counts the guest email, which is not an address field at all", async () => {
        const { wrapper } = await stalled();

        expect(wrapper.find("[data-payment-blockers]").text()).toContain("Email address");
    });

    it("gives way to the real payment section the moment the methods land", async () => {
        const { wrapper, checkout } = await stalled({ isLoggedIn: true, customerEmail: "ada@shop.test" });
        expect(wrapper.find("#onepage-payment-pending").exists()).toBe(true);

        checkout.paymentMethods = [CHECKMO];
        await nextTick();

        expect(wrapper.find("#onepage-payment-pending").exists()).toBe(false);
        expect(wrapper.find("#onepage-payment").exists()).toBe(true);
    });

    it("hands the screen back to the notice when a field is emptied after payment was up", async () => {
        const { wrapper, checkout } = await stalled({
            address: COMPLETE_ADDRESS,
            isLoggedIn: true,
            customerEmail: "ada@shop.test",
        });
        expect(checkout.paymentMethods).toHaveLength(1);
        expect(wrapper.find("#onepage-payment").exists()).toBe(true);

        checkout.shippingAddress.firstname = "";
        await settle();

        expect(wrapper.find("#onepage-payment").exists()).toBe(false);
        expect(wrapper.find("[data-payment-blockers]").text()).toContain("First name");
    });

    it("sends the shopper to the field behind the item they clicked", async () => {
        const { wrapper } = await stalled({ isLoggedIn: true, customerEmail: "ada@shop.test" });

        await wrapper.find('[data-blocker="firstname"]').trigger("click");

        expect(wrapper.find("[data-address-form-stub]").attributes("data-invalid-fields")).toBe("firstname");
    });

    it("never puts up an empty card: with nothing missing, the notice stays away", async () => {
        const { wrapper, checkout } = await stalled({
            address: COMPLETE_ADDRESS,
            isLoggedIn: true,
            customerEmail: "ada@shop.test",
        });

        expect(checkout.missingAddressFields).toEqual([]);
        expect(checkout.emailReady).toBe(true);
        expect(wrapper.find("#onepage-payment-pending").exists()).toBe(false);
    });
});
