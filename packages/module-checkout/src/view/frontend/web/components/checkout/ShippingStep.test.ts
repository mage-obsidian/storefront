import { describe, it, expect, beforeEach, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { setActivePinia, createPinia } from "pinia";
import ShippingStep from "./ShippingStep.vue";
import { useCheckout } from "MageObsidian_Checkout::js/useCheckout";

const CONFIG = {
    isLoggedIn: false,
    restBaseUrl: "https://shop.test/rest/default/V1/",
    maskedCartId: "mask42",
    currencyFormat: "$%s",
    defaultCountry: "US",
    quote: { items: [], subtotal: "", grandTotal: "" },
};

const DIRECTORY = {
    countries: [{ value: "US", label: "United States" }],
    regions: { US: [{ id: 12, code: "CA", name: "California" }] },
    statesRequired: ["US"],
    displayAllRegions: false,
    defaultCountry: "US",
};

const FLATRATE = { carrier_code: "flatrate", method_code: "flatrate", carrier_title: "Flat Rate", method_title: "Fixed", amount: 5, available: true };

function mockFetch(response: unknown, ok = true, status = 200) {
    globalThis.fetch = vi.fn().mockResolvedValue({ ok, status, json: () => Promise.resolve(response) });
}

const flush = () => new Promise((r) => setTimeout(r));

describe("ShippingStep", () => {
    let pinia;

    beforeEach(() => {
        pinia = createPinia();
        setActivePinia(pinia);
        vi.restoreAllMocks();
        useCheckout().init(CONFIG);
    });

    function render() {
        return mount(ShippingStep, {
            props: { directory: DIRECTORY },
            global: { plugins: [pinia] },
        });
    }

    it("estimates rates and lists them with formatted prices", async () => {
        mockFetch([FLATRATE]);
        const wrapper = render();

        await wrapper.find("button").trigger("click");
        await flush();
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain("Flat Rate");
        expect(wrapper.text()).toContain("$5.00");
        expect(wrapper.find('[role="radiogroup"]').exists()).toBe(true);
    });

    it("advances to payment after saving the chosen method", async () => {
        mockFetch([FLATRATE]);
        const wrapper = render();
        await wrapper.find("button").trigger("click");
        await flush();
        await wrapper.vm.$nextTick();

        mockFetch({ payment_methods: [{ code: "checkmo", title: "Check" }], totals: { grand_total: 39 } });
        const buttons = wrapper.findAll("button");
        await buttons[buttons.length - 1].trigger("click");
        await flush();

        expect(useCheckout().step).toBe("payment");
        expect(useCheckout().paymentMethods).toEqual([{ code: "checkmo", title: "Check" }]);
    });

    it("shows free for a zero-amount rate", async () => {
        mockFetch([{ ...FLATRATE, carrier_title: "Free Shipping", amount: 0 }]);
        const wrapper = render();
        await wrapper.find("button").trigger("click");
        await flush();
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain("Free");
    });
});
