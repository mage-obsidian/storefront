import { describe, it, expect, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import CompareCount from "./CompareCount.vue";
import { __setSection, __reset } from "MageObsidian_ModernFrontend::js/customer-data";

beforeEach(() => __reset());

describe("CompareCount", () => {
    it("renders the compare item count and an sr-only live region", () => {
        __setSection("compare-products", { items: [{ id: 1 }, { id: 2 }, { id: 3 }] });

        const wrapper = mount(CompareCount, { props: { label: "in your compare list" } });

        expect(wrapper.text()).toContain("3");
        const live = wrapper.get('[role="status"]');
        expect(live.attributes("aria-live")).toBe("polite");
        expect(live.text()).toContain("3 in your compare list");
    });

    it("hides the badge when nothing is being compared", () => {
        const wrapper = mount(CompareCount);
        expect(wrapper.find(".bg-accent").exists()).toBe(false);
        expect(wrapper.get('[role="status"]').text()).toContain("0");
    });
});
