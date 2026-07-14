import { describe, it, expect, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import { setActivePinia, createPinia } from "pinia";
import Agreements from "./Agreements.vue";
import { useCheckout } from "../../js/useCheckout.ts";

function render(agreements: unknown) {
    const pinia = createPinia();
    setActivePinia(pinia);
    const checkout = useCheckout();
    checkout.init({ agreements });
    const wrapper = mount(Agreements, { global: { plugins: [pinia] } });
    return { wrapper, checkout };
}

describe("Agreements.vue", () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it("renders nothing when agreements are disabled", () => {
        const { wrapper } = render({ enabled: false, items: [] });
        expect(wrapper.find("section").exists()).toBe(false);
    });

    it("renders a manual agreement as a checkbox and records acceptance", async () => {
        const { wrapper, checkout } = render({
            enabled: true,
            items: [{ agreementId: 7, mode: 1, content: "Full terms", checkboxText: "I agree" }],
        });

        const checkbox = wrapper.find('input[type="checkbox"]');
        expect(checkbox.exists()).toBe(true);
        expect(wrapper.text()).toContain("I agree");

        await checkbox.setValue(true);
        expect(checkout.acceptedAgreements).toContain(7);
    });

    it("renders an auto agreement as text without a checkbox", () => {
        const { wrapper } = render({
            enabled: true,
            items: [{ agreementId: 9, mode: 0, content: "", checkboxText: "By ordering you agree" }],
        });

        expect(wrapper.find('input[type="checkbox"]').exists()).toBe(false);
        expect(wrapper.text()).toContain("By ordering you agree");
    });
});
