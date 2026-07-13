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

function render(configOverrides = {}) {
    const pinia = createPinia();
    setActivePinia(pinia);
    const checkout = useCheckout();
    checkout.init({ ...CONFIG, ...configOverrides });
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

    it("estimates shipping reactively (debounced, once) when the address is complete", async () => {
        const { checkout } = render();
        const estimate = vi.spyOn(checkout, "estimateShipping").mockResolvedValue(true);

        checkout.shippingAddress = { ...COMPLETE_ADDRESS };
        await nextTick();
        expect(estimate).not.toHaveBeenCalled();

        vi.advanceTimersByTime(400);
        expect(estimate).toHaveBeenCalledTimes(1);

        // No address change → no second call.
        vi.advanceTimersByTime(400);
        expect(estimate).toHaveBeenCalledTimes(1);
    });

    it("does not estimate while the rating fields are incomplete", async () => {
        const { checkout } = render();
        const estimate = vi.spyOn(checkout, "estimateShipping").mockResolvedValue(true);

        checkout.shippingAddress = { ...COMPLETE_ADDRESS, postcode: "" };
        await nextTick();
        vi.advanceTimersByTime(400);
        expect(estimate).not.toHaveBeenCalled();
    });

    it("saves shipping-information reactively once a method is selected on a complete address", async () => {
        const { checkout } = render();
        vi.spyOn(checkout, "estimateShipping").mockResolvedValue(true);
        const save = vi.spyOn(checkout, "saveShipping").mockResolvedValue(true);

        checkout.email = "guest@shop.test";
        checkout.shippingAddress = { ...COMPLETE_ADDRESS };
        checkout.selectMethod(FLATRATE);
        await nextTick();
        vi.advanceTimersByTime(400);

        expect(save).toHaveBeenCalledTimes(1);
    });
});
