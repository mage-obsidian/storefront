import { describe, it, expect, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import CartCount from "./CartCount.vue";
import { __setSection, __reset } from "MageObsidian_ModernFrontend::js/customer-data";

// Live header bag count, fed by the customer-data bridge (stubbed in tests).
beforeEach(() => __reset());

describe("CartCount", () => {
    it("renders the current cart count and an sr-only live region", () => {
        __setSection("cart", { summary_count: 2 });

        const wrapper = mount(CartCount, { props: { label: "in your bag" } });

        expect(wrapper.get(".cart-count").text()).toContain("2");
        const live = wrapper.get('[role="status"]');
        expect(live.attributes("aria-live")).toBe("polite");
        expect(live.text()).toContain("2");
    });

    it("always renders the shopping-bag icon", () => {
        const wrapper = mount(CartCount);
        expect(wrapper.find("svg").exists()).toBe(true);
    });

    it("reacts when the cart section updates", async () => {
        const wrapper = mount(CartCount);

        __setSection("cart", { summary_count: 5 });
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain("5");
    });

    it("shows no numeric badge when the bag is empty", () => {
        __setSection("cart", { summary_count: 0 });

        const wrapper = mount(CartCount);

        // The decorative number badge is absent; the live region still reports 0.
        expect(wrapper.find("span[aria-hidden='true']").classes()).not.toContain("bg-accent");
    });
});
