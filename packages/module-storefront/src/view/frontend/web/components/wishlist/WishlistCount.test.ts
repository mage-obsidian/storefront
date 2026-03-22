import { describe, it, expect, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import WishlistCount from "./WishlistCount.vue";
import { __setSection, __reset } from "MageObsidian_ModernFrontend::js/customer-data";

beforeEach(() => __reset());

describe("WishlistCount", () => {
    it("renders the saved-product count and an sr-only live region", () => {
        __setSection("wishlist", { saved: { "12": "/r/5", "34": "/r/9" } });

        const wrapper = mount(WishlistCount, { props: { label: "in your wish list" } });

        expect(wrapper.text()).toContain("2");
        const live = wrapper.get('[role="status"]');
        expect(live.attributes("aria-live")).toBe("polite");
        expect(live.text()).toContain("2 in your wish list");
    });

    it("hides the badge when the wish list is empty", () => {
        const wrapper = mount(WishlistCount);
        expect(wrapper.find(".bg-accent").exists()).toBe(false);
        expect(wrapper.get('[role="status"]').text()).toContain("0");
    });
});
