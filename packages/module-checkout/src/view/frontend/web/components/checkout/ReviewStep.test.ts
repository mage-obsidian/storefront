import { describe, it, expect, beforeEach, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { setActivePinia, createPinia } from "pinia";
import ReviewStep from "./ReviewStep.vue";
import { useCheckout } from "MageObsidian_Checkout::js/useCheckout";

const CONFIG = {
    isLoggedIn: false,
    restBaseUrl: "https://shop.test/rest/default/V1/",
    maskedCartId: "mask42",
    currencyFormat: "$%s",
    successUrl: "https://shop.test/checkout/onepage/success/",
    quote: { items: [], subtotal: "", grandTotal: "" },
};

function mockFetch(response: unknown, ok = true, status = 200) {
    globalThis.fetch = vi.fn().mockResolvedValue({ ok, status, json: () => Promise.resolve(response) });
}

const flush = () => new Promise((r) => setTimeout(r));

describe("ReviewStep", () => {
    let pinia;
    let checkout: ReturnType<typeof useCheckout>;

    beforeEach(() => {
        pinia = createPinia();
        setActivePinia(pinia);
        vi.restoreAllMocks();
        checkout = useCheckout();
        checkout.init(CONFIG);
        checkout.email = "guest@shop.test";
        checkout.paymentMethods = [{ code: "checkmo", title: "Check / Money order" }];
        checkout.selectedPayment = "checkmo";
    });

    function render() {
        return mount(ReviewStep, { global: { plugins: [pinia] } });
    }

    it("recaps the email and chosen payment", () => {
        const wrapper = render();
        expect(wrapper.text()).toContain("guest@shop.test");
        expect(wrapper.text()).toContain("Check / Money order");
    });

    it("applies a coupon and then shows it as removable", async () => {
        mockFetch({ grand_total: 54, total_segments: [] });
        const wrapper = render();

        await wrapper.find("#coupon-code").setValue("SAVE10");
        await wrapper.find("form").trigger("submit");
        await flush();
        await wrapper.vm.$nextTick();

        expect(checkout.appliedCoupon).toBe("SAVE10");
        expect(wrapper.text()).toContain("SAVE10");
        expect(wrapper.find("#coupon-code").exists()).toBe(false);
    });

    it("places the order when the place-order button is clicked", async () => {
        Object.defineProperty(window, "location", { value: { assign: vi.fn() }, writable: true });
        mockFetch(999);
        const wrapper = render();

        const buttons = wrapper.findAll("button");
        await buttons[buttons.length - 1].trigger("click");
        await flush();

        expect((globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0][0]).toContain("payment-information");
    });

    it("will not let the order close while the quote is behind the screen", async () => {
        mockFetch({ payment_methods: [{ code: "checkmo", title: "Check" }], totals: {} });
        checkout.selectMethod({ carrier_code: "flatrate", method_code: "flatrate" });
        await checkout.saveShipping();
        checkout.shippingAddress.city = "Lyon";
        const wrapper = render();

        expect(checkout.shippingDirty).toBe(true);
        expect(wrapper.find("[data-place-order]").attributes("disabled")).toBeDefined();
        expect(wrapper.find("[data-shipping-pending]").exists()).toBe(true);
    });

    it("tells a shopper whose checkout will not sync itself what to do about it", async () => {
        mockFetch({ payment_methods: [{ code: "checkmo", title: "Check" }], totals: {} });
        checkout.selectMethod({ carrier_code: "flatrate", method_code: "flatrate" });
        await checkout.saveShipping();
        checkout.shippingAddress.city = "Lyon";
        const wrapper = render();

        expect(wrapper.find("[data-shipping-pending]").text()).toMatch(/go back to the shipping step/i);
    });

    it("says it is working while the sync it owes is still owed", async () => {
        mockFetch({ payment_methods: [{ code: "checkmo", title: "Check" }], totals: {} });
        checkout.selectMethod({ carrier_code: "flatrate", method_code: "flatrate" });
        await checkout.saveShipping();
        checkout.shippingAddress.city = "Lyon";
        checkout.scheduleShippingSync();
        const wrapper = render();

        expect(wrapper.find("[data-shipping-pending]").text()).toMatch(/confirming your shipping/i);
        checkout.cancelShippingSync();
    });

    it("says nothing about shipping once the quote holds what is on screen", () => {
        const wrapper = render();

        expect(wrapper.find("[data-place-order]").attributes("disabled")).toBeUndefined();
        expect(wrapper.find("[data-shipping-pending]").exists()).toBe(false);
    });

    it("locks the coupon actions while the request is in flight", async () => {
        let settle: (value: unknown) => void = () => {};
        globalThis.fetch = vi.fn().mockReturnValue(new Promise((resolve) => { settle = resolve; }));
        const wrapper = render();

        await wrapper.find("#coupon-code").setValue("SAVE10");
        void wrapper.find('button[type="submit"]').trigger("submit");
        await flush();

        const apply = wrapper.find('button[type="submit"]');
        expect(apply.attributes("disabled")).toBeDefined();
        expect(apply.find(".btn__spinner").exists()).toBe(true);

        settle({ ok: true, status: 200, json: () => Promise.resolve(true) });
    });

    it("sizes the coupon field and its action off the same primitives", () => {
        const wrapper = render();

        expect(wrapper.find("#coupon-code").classes()).toContain("field__control");
        const apply = wrapper.find('button[type="submit"]');
        expect(apply.classes()).toContain("btn");
        expect(apply.classes()).not.toContain("btn--sm");
    });

    it("gives place order the full width of a phone and anchors it above the dock", () => {
        const wrapper = render();
        const button = wrapper.find("[data-place-order]");

        expect(button.classes()).toContain("btn--block");
        expect(button.classes()).toContain("lg:w-fit");
        expect(button.element.closest(".checkout-cta")).not.toBeNull();
    });
});
