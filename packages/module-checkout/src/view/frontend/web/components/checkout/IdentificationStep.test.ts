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
});
