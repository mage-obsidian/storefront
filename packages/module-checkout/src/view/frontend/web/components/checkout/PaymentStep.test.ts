import { describe, it, expect, beforeEach, vi } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { setActivePinia, createPinia } from "pinia";
import PaymentStep from "./PaymentStep.vue";
import { useCheckout } from "MageObsidian_Checkout::js/useCheckout";

const PROBE = "../../../../../Test/Js/stubs/ProbeRenderer.vue";
const BROKEN = "../../../../../Test/Js/stubs/BrokenRenderer.vue";
const LIFECYCLE = "../../../../../Test/Js/stubs/LifecycleRenderer.vue";
const CARDS = "../../../../../Test/Js/stubs/CardsRenderer.vue";

const settle = async (): Promise<void> => {
    for (let pass = 0; pass < 10; pass += 1) {
        await new Promise((resolve) => setTimeout(resolve, 10));
        await flushPromises();
    }
};

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

    it("leaves the selected chrome to CSS, so the card cannot disagree with the input", async () => {
        const wrapper = render();
        const card = wrapper.findAll(".field-radio-card")[0];

        await wrapper.findAll('input[type="radio"]')[0].setValue();

        expect(card.classes()).toContain("field-radio-card");
        expect(card.classes()).not.toContain("border-ink");
        expect(card.classes()).not.toContain("border-ash-300");
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

    it("gives its advance button the full width of a phone", () => {
        const button = render().find(".checkout-cta");

        expect(button.exists()).toBe(true);
        expect(button.classes()).toContain("btn--block");
        expect(button.classes()).toContain("lg:w-fit");
    });

    it("draws a method with a declared renderer with its own component", async () => {
        checkout.paymentRenderers = { checkmo: { component: PROBE } };
        const wrapper = render();
        await settle();

        expect(wrapper.find("[data-probe-renderer=checkmo]").exists()).toBe(true);
        expect(wrapper.find('input[type="radio"][value="checkmo"]').exists()).toBe(false);
    });

    it("leaves a method without a renderer on the generic radio", async () => {
        checkout.paymentRenderers = { checkmo: { component: PROBE } };
        const wrapper = render();
        await settle();

        expect(wrapper.find('input[type="radio"][value="free"]').exists()).toBe(true);
        expect(wrapper.find("[data-probe-renderer=free]").exists()).toBe(false);
    });

    it("hands the renderer the native config published for its method", async () => {
        checkout.paymentRenderers = { checkmo: { component: PROBE } };
        checkout.paymentConfig = { checkmo: { payableTo: "The Store" } };
        const wrapper = render();
        await settle();

        expect(checkout.configFor("checkmo")).toEqual({ payableTo: "The Store" });
        expect(wrapper.find("[data-probe-renderer=checkmo]").exists()).toBe(true);
    });

    it("takes the method away when its renderer cannot mount, and keeps the rest usable", async () => {
        checkout.paymentRenderers = { checkmo: { component: BROKEN } };
        const wrapper = render();
        await settle();

        expect(wrapper.text()).not.toContain("Check / Money order");
        expect(wrapper.find('input[type="radio"][value="free"]').exists()).toBe(true);
        expect(checkout.selectedPayment).toBe("free");
    });

    it("says there is no method available once every renderer has failed", async () => {
        checkout.paymentMethods = [{ code: "checkmo", title: "Check / Money order" }];
        checkout.paymentRenderers = { checkmo: { component: BROKEN } };
        const wrapper = render();
        await settle();

        expect(wrapper.text()).toContain("No payment methods available.");
        expect(checkout.selectedPayment).toBe("");
    });

    it("holds the order back while the renderer says its method is not ready", async () => {
        checkout.paymentRenderers = { checkmo: { component: LIFECYCLE } };
        const wrapper = render();
        await settle();

        expect(checkout.selectedMethodReady).toBe(false);
        expect(wrapper.find("[data-method-blocker]").text()).toBe("Enter the transfer reference.");
        expect(await checkout.placeOrder()).toBeNull();
        expect(checkout.orderError).toBe("Enter the transfer reference.");
    });

    it("lets the order through once the renderer says the method is ready", async () => {
        checkout.paymentRenderers = { checkmo: { component: LIFECYCLE } };
        const wrapper = render();
        await settle();
        await wrapper.find("[data-lifecycle-complete]").trigger("click");

        expect(checkout.selectedMethodReady).toBe(true);
        expect(wrapper.find("[data-method-blocker]").exists()).toBe(false);
        expect(checkout.stateFor("checkmo").data).toEqual({ reference: "REF-1" });
    });

    it("stops offering to place the order when the renderer takes the action over", async () => {
        checkout.paymentRenderers = { checkmo: { component: LIFECYCLE } };
        const wrapper = render();
        await settle();

        expect(checkout.placeOrderAvailable).toBe(true);
        await wrapper.find("[data-lifecycle-takeover]").trigger("click");

        expect(checkout.placeOrderAvailable).toBe(false);
        expect(await checkout.placeOrder()).toBeNull();
    });

    it("draws each vault method with the renderer declared for it", async () => {
        checkout.paymentMethods = [
            { code: "acme_cc_vault", title: "Acme saved cards" },
            { code: "other_cc_vault", title: "Other saved cards" },
        ];
        checkout.selectedPayment = "acme_cc_vault";
        checkout.paymentRenderers = {
            acme_cc_vault: { component: CARDS },
            other_cc_vault: { component: PROBE },
        };
        checkout.paymentData = { acme_cc_vault: { tokens: [{ publicHash: "h1", last4: "1111" }] } };
        const wrapper = render();
        await settle();

        expect(wrapper.find("[data-cards-renderer=acme_cc_vault]").exists()).toBe(true);
        expect(wrapper.find("[data-cards-renderer=other_cc_vault]").exists()).toBe(false);
        expect(wrapper.find("[data-probe-renderer=other_cc_vault]").exists()).toBe(true);
    });

    it("hands a vault renderer the cards its own method was given", async () => {
        checkout.paymentMethods = [{ code: "acme_cc_vault", title: "Acme saved cards" }];
        checkout.selectedPayment = "acme_cc_vault";
        checkout.paymentRenderers = { acme_cc_vault: { component: CARDS } };
        checkout.paymentData = { acme_cc_vault: { tokens: [{ publicHash: "h1", last4: "1111" }] } };
        const wrapper = render();
        await settle();

        expect(wrapper.find("[data-card=h1]").exists()).toBe(true);
    });

    it("stops offering a method the checkout withdrew and leaves it unselected", async () => {
        const wrapper = render();
        expect(checkout.selectedPayment).toBe("checkmo");

        checkout.withdrawMethod("checkmo");
        await settle();

        expect(wrapper.text()).not.toContain("Check / Money order");
        expect(wrapper.find('input[type="radio"][value="free"]').exists()).toBe(true);
        expect(checkout.selectedPayment).toBe("free");
    });

    it("says there is nothing to pay with once every method has been withdrawn", async () => {
        const wrapper = render();

        checkout.withdrawMethod("checkmo");
        checkout.withdrawMethod("free");
        await settle();

        expect(wrapper.text()).toContain("No payment methods available.");
        expect(checkout.selectedPayment).toBe("");
        expect(checkout.placeOrderAvailable).toBe(false);
    });

    it("never offers a method the server already withdrew", async () => {
        checkout.withdrawnMethods = ["checkmo"];
        const wrapper = render();
        await settle();

        expect(wrapper.text()).not.toContain("Check / Money order");
        expect(wrapper.text()).toContain("No Payment Information Required");
    });
});
