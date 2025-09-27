import { describe, it, expect, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import { setActivePinia, createPinia } from "pinia";
import Checkout from "./Checkout.vue";
import { reload, __reset } from "../../../../../Test/Js/stubs/customerData.ts";

const CONFIG = {
    isLoggedIn: false,
    customerEmail: "",
    quote: {
        items: [{ id: 1, name: "Joust Duffle Bag", qty: 2, rowTotal: "$68.00", image: "" }],
        subtotal: "$68.00",
        grandTotal: "$73.00",
    },
};

describe("Checkout.vue", () => {
    let pinia;

    beforeEach(() => {
        pinia = createPinia();
        setActivePinia(pinia);
        __reset();
    });

    function render(config = CONFIG, labels = {}) {
        return mount(Checkout, {
            props: { config, labels },
            global: { plugins: [pinia] },
        });
    }

    it("renders the four-step rail with the current step marked", () => {
        const wrapper = render();
        const steps = wrapper.findAll("ol li");
        expect(steps).toHaveLength(4);

        const current = wrapper.findAll('[aria-current="step"]');
        expect(current).toHaveLength(1);
        expect(current[0].text()).toContain("Identification");
    });

    it("paints the server-primed order summary without any fetch", () => {
        const wrapper = render();
        expect(wrapper.text()).toContain("Joust Duffle Bag");
        expect(wrapper.text()).toContain("× 2");
        expect(wrapper.text()).toContain("$68.00");
        expect(wrapper.text()).toContain("$73.00");
    });

    it("starts a logged-in customer on the shipping step", () => {
        const wrapper = render({ ...CONFIG, isLoggedIn: true, customerEmail: "ada@shop.test" });
        const current = wrapper.find('[aria-current="step"]');
        expect(current.text()).toContain("Shipping");
    });

    it("uses provided i18n labels", () => {
        const wrapper = render(CONFIG, { stepShipping: "Envío", summary: "Resumen" });
        expect(wrapper.text()).toContain("Envío");
        expect(wrapper.text()).toContain("Resumen");
    });

    it("reconciles the cart section from the authoritative quote on mount", () => {
        render();
        expect(reload.calls).toContainEqual([["cart"]]);
    });
});
