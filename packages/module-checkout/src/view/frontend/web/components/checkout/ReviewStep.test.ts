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
});
