import { describe, it, expect, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import CartCount from "./CartCount.vue";
import { __setSection, __reset } from "MageObsidian_ModernFrontend::js/customer-data";
import events, { __reset as __resetEvents } from "MageObsidian_ModernFrontend::js/events";

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

        expect(wrapper.find("span[aria-hidden='true']").exists()).toBe(false);
        expect(wrapper.get("[role='status']").text()).toBe("0 in your bag");
    });

    it("floats the badge over the icon so the header never reflows", () => {
        __setSection("cart", { summary_count: 3 });

        const badge = mount(CartCount).get("span[aria-hidden='true']");

        expect(badge.classes()).toContain("absolute");
        expect(mount(CartCount).get(".cart-count").classes()).not.toContain("gap-2");
    });
});

describe("CartCount while the cart is syncing", () => {
    // The tracker is a page-wide singleton, so every test closes what it opened.
    const close = (event = "cart_add_after") =>
        events.dispatch(event, { cancelled: false, result: { ok: true } });

    beforeEach(() => __resetEvents());

    it("shows the ring from the moment the mutation is announced", async () => {
        const wrapper = mount(CartCount);
        expect(wrapper.find(".cart-count__ring").exists()).toBe(false);

        await events.dispatch("cart_add_before", { cancelled: false });
        await wrapper.vm.$nextTick();

        expect(wrapper.find(".cart-count__ring").exists()).toBe(true);
        expect(wrapper.get(".cart-count").classes()).toContain("is-syncing");

        await close();
    });

    it("drops it once the mutation reports back", async () => {
        const wrapper = mount(CartCount);
        await events.dispatch("cart_add_before", { cancelled: false });
        await events.dispatch("cart_add_after", { cancelled: false, result: { ok: true } });
        await wrapper.vm.$nextTick();

        expect(wrapper.find(".cart-count__ring").exists()).toBe(false);
    });

    it("drops it when a before observer cancelled, since no after will come", async () => {
        const wrapper = mount(CartCount);
        await events.dispatch("cart_add_before", { cancelled: true });
        await wrapper.vm.$nextTick();

        expect(wrapper.find(".cart-count__ring").exists()).toBe(false);
    });

    it("announces the sync to assistive tech instead of a stale count", async () => {
        __setSection("cart", { summary_count: 2 });
        const wrapper = mount(CartCount, { props: { syncingLabel: "Updating your bag" } });

        await events.dispatch("cart_add_before", { cancelled: false });
        await wrapper.vm.$nextTick();

        expect(wrapper.get("[role='status']").text()).toBe("Updating your bag");

        await close();
    });

    it("ignores a mutation in another domain", async () => {
        const wrapper = mount(CartCount);

        await events.dispatch("wishlist_add_before", { cancelled: false });
        await wrapper.vm.$nextTick();

        expect(wrapper.find(".cart-count__ring").exists()).toBe(false);

        await close("wishlist_add_after");
    });
});
