import { describe, it, expect, beforeEach, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { setActivePinia, createPinia } from "pinia";
import IdentificationStep from "./IdentificationStep.vue";
import { useCheckout } from "MageObsidian_Checkout::js/useCheckout";

const CONFIG = {
    isLoggedIn: false,
    restBaseUrl: "https://shop.test/rest/default/V1/",
    maskedCartId: "mask42",
    quote: { items: [], subtotal: "", grandTotal: "" },
};

function mockFetch(response: unknown, ok = true, status = 200) {
    globalThis.fetch = vi.fn().mockResolvedValue({ ok, status, json: () => Promise.resolve(response) });
}

describe("IdentificationStep", () => {
    let pinia;

    beforeEach(() => {
        pinia = createPinia();
        setActivePinia(pinia);
        vi.restoreAllMocks();
        useCheckout().init({ ...CONFIG, guestCheckoutLogin: true });
    });

    function render() {
        return mount(IdentificationStep, {
            props: { loginUrl: "https://shop.test/customer/account/login" },
            global: { plugins: [pinia] },
        });
    }

    it("shows an email the store learns after mount, as a reload restores it", async () => {
        const wrapper = render();
        expect((wrapper.find("input[type=email]").element as HTMLInputElement).value).toBe("");

        useCheckout().email = "grace@shop.test";
        await wrapper.vm.$nextTick();

        expect((wrapper.find("input[type=email]").element as HTMLInputElement).value).toBe("grace@shop.test");
    });

    it("rejects an invalid email without advancing", async () => {
        const wrapper = render();
        await wrapper.find("input[type=email]").setValue("not-an-email");
        await wrapper.find("form").trigger("submit");

        expect(wrapper.find('[role="alert"]').exists()).toBe(true);
        expect(useCheckout().step).toBe("identification");
    });

    it("continues as guest when the email is available", async () => {
        mockFetch(true);
        const wrapper = render();
        await wrapper.find("input[type=email]").setValue("new@shop.test");
        await wrapper.find("form").trigger("submit");
        await new Promise((r) => setTimeout(r));

        const checkout = useCheckout();
        expect(checkout.email).toBe("new@shop.test");
        expect(checkout.step).toBe("shipping");
    });

    it("surfaces a sign-in link when an account already exists", async () => {
        mockFetch(false);
        const wrapper = render();
        await wrapper.find("input[type=email]").setValue("ada@shop.test");
        await wrapper.find("form").trigger("submit");
        await new Promise((r) => setTimeout(r));

        expect(wrapper.text()).toContain("account already exists");
        expect(wrapper.find("a[href*='login']").exists()).toBe(true);
        expect(useCheckout().step).toBe("identification");
    });

    it("offers the way in to a customer who already has an account", () => {
        const wrapper = mount(IdentificationStep, {
            props: { loginUrl: "https://shop.test/customer/account/login" },
            global: { plugins: [pinia] },
        });

        const prompt = wrapper.find("[data-signin-prompt]");
        expect(prompt.exists()).toBe(true);
        expect(prompt.find("a").attributes("href")).toBe("https://shop.test/customer/account/login");
    });

    it("says nothing about signing in when there is nowhere to send them", () => {
        const wrapper = mount(IdentificationStep, { global: { plugins: [pinia] } });

        expect(wrapper.find("[data-signin-prompt]").exists()).toBe(false);
    });

    it("gives its advance button the full width of a phone", () => {
        const button = render().find('button[type="submit"]');

        expect(button.classes()).toContain("btn--block");
        expect(button.classes()).toContain("lg:w-fit");
        expect(button.classes()).toContain("checkout-cta");
    });
});
