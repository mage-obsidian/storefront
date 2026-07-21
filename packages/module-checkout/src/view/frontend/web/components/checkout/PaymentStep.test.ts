import { describe, it, expect, beforeEach, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { setActivePinia, createPinia } from "pinia";
import PaymentStep from "./PaymentStep.vue";
import { useCheckout } from "MageObsidian_Checkout::js/useCheckout";

const CONFIG = {
    isLoggedIn: false,
    restBaseUrl: "https://shop.test/rest/default/V1/",
    maskedCartId: "mask42",
    defaultCountry: "US",
    quote: { items: [], subtotal: "", grandTotal: "" },
};

const DIRECTORY = {
    countries: [{ value: "US", label: "United States" }],
    regions: {},
    statesRequired: [],
    displayAllRegions: false,
    defaultCountry: "US",
};

describe("PaymentStep", () => {
    let pinia;
    let checkout: ReturnType<typeof useCheckout>;

    beforeEach(() => {
        pinia = createPinia();
        setActivePinia(pinia);
        vi.restoreAllMocks();
        checkout = useCheckout();
        checkout.init(CONFIG);
        checkout.paymentMethods = [
            { code: "checkmo", title: "Check / Money order" },
            { code: "free", title: "No Payment Information Required" },
        ];
        checkout.selectedPayment = "checkmo";
    });

    function render() {
        return mount(PaymentStep, { props: { directory: DIRECTORY }, global: { plugins: [pinia] } });
    }

    it("lists the native payment methods as a radio group", () => {
        const wrapper = render();
        expect(wrapper.find('[role="radiogroup"]').exists()).toBe(true);
        expect(wrapper.text()).toContain("Check / Money order");
        expect(wrapper.text()).toContain("No Payment Information Required");
    });

    it("selects a payment method on change", async () => {
        const wrapper = render();
        const radios = wrapper.findAll('input[type="radio"]');
        await radios[1].setValue();
        expect(checkout.selectedPayment).toBe("free");
    });

    it("hides the billing form while same-as-shipping is checked", async () => {
        const wrapper = render();
        expect(wrapper.find("[data-address-form-stub]").exists()).toBe(false);

        await wrapper.find('input[type="checkbox"]').setValue(false);
        expect(wrapper.find("[data-address-form-stub]").exists()).toBe(true);
    });

    it("advances to review when same-as-shipping is on", async () => {
        const wrapper = render();
        await wrapper.find("button").trigger("click");
        expect(checkout.step).toBe("review");
    });

    it("in per-method mode keeps billing hidden until a method is selected", async () => {
        checkout.displayBillingOnPayment = true;
        checkout.selectedPayment = "";
        const wrapper = render();
        expect(wrapper.find('section[aria-labelledby="billing-heading"]').exists()).toBe(false);

        checkout.selectedPayment = "checkmo";
        await wrapper.vm.$nextTick();
        const billing = wrapper.find('section[aria-labelledby="billing-heading"]');
        expect(billing.exists()).toBe(true);
        expect(billing.text()).toContain("Check / Money order");
    });

    it("in payment-page mode shows the shared billing form regardless of selection", () => {
        checkout.displayBillingOnPayment = false;
        checkout.selectedPayment = "";
        const wrapper = render();
        const billing = wrapper.find('section[aria-labelledby="billing-heading"]');
        expect(billing.exists()).toBe(true);
        expect(billing.text()).not.toContain("Check / Money order");
    });
});
