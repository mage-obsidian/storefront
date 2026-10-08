// This file is part of the MageObsidian - Sales project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import ReorderSidebar from "./ReorderSidebar.vue";
import { __reset, __setSection } from "MageObsidian_ModernFrontend::js/customer-data";
import { STUB_FORM_KEY } from "../../../../../Test/Js/stubs/form-key-provider";

const labels = {
    title: "Recently Ordered",
    subtitle: "Last Ordered Items",
    addToCart: "Add to Cart",
    viewAll: "View All",
    unavailable: "Product is not salable.",
    choose: "Please select at least one item to add.",
};

const props = { labels, action: "https://shop.test/checkout/cart/addgroup/", viewAllUrl: "https://shop.test/sales/order/history/" };

const line = (id: number, overrides: Record<string, unknown> = {}) => ({
    id,
    name: `Line ${id}`,
    url: `https://shop.test/line-${id}.html`,
    is_saleable: true,
    product_id: id + 100,
    ...overrides,
});

beforeEach(() => __reset());

describe("ReorderSidebar", () => {
    it("renders nothing when the customer has no last order", () => {
        __setSection("last-ordered-items", { items: [] });

        const wrapper = mount(ReorderSidebar, { props });

        expect(wrapper.find("[data-reorder-sidebar]").exists()).toBe(false);
    });

    it("lists the last order's items with a checkbox each, named for the line", () => {
        __setSection("last-ordered-items", { items: [line(1), line(2)] });

        const wrapper = mount(ReorderSidebar, { props });

        const boxes = wrapper.findAll("input[type=checkbox]");
        expect(boxes).toHaveLength(2);
        expect(boxes[0].attributes("name")).toBe("order_items[]");
        expect((boxes[1].element as HTMLInputElement).value).toBe("2");
        expect(wrapper.text()).toContain("Line 1");
        expect(wrapper.get("label[for='" + boxes[0].attributes("id") + "']").text()).toContain("Line 1");
    });

    it("posts the chosen lines to the add-group action with the form key", () => {
        __setSection("last-ordered-items", { items: [line(1)] });

        const wrapper = mount(ReorderSidebar, { props });

        const form = wrapper.get("form");
        expect(form.attributes("action")).toBe("https://shop.test/checkout/cart/addgroup/");
        expect(form.attributes("method")).toBe("post");
        expect((form.get("input[name=form_key]").element as HTMLInputElement).value).toBe(STUB_FORM_KEY);
    });

    it("disables a line that is not salable and says why", () => {
        __setSection("last-ordered-items", { items: [line(1), line(2, { is_saleable: false })] });

        const wrapper = mount(ReorderSidebar, { props });

        const boxes = wrapper.findAll("input[type=checkbox]");
        expect(boxes[0].attributes("disabled")).toBeUndefined();
        expect(boxes[1].attributes("disabled")).toBeDefined();
        expect(boxes[1].attributes("title")).toBe("Product is not salable.");
    });

    it("offers no bag button when nothing can be bought", () => {
        __setSection("last-ordered-items", { items: [line(1, { is_saleable: false })] });

        const wrapper = mount(ReorderSidebar, { props });

        expect(wrapper.find("button[type=submit]").exists()).toBe(false);
    });

    it("refuses to submit with no line chosen and asks for one", async () => {
        __setSection("last-ordered-items", { items: [line(1), line(2)] });
        const wrapper = mount(ReorderSidebar, { props });

        const submit = new Event("submit", { cancelable: true });
        wrapper.get("form").element.dispatchEvent(submit);
        await wrapper.vm.$nextTick();

        expect(submit.defaultPrevented).toBe(true);
        expect(wrapper.get("[role=alert]").text()).toBe("Please select at least one item to add.");
    });

    it("lets the submit through once a line is chosen", async () => {
        __setSection("last-ordered-items", { items: [line(1), line(2)] });
        const wrapper = mount(ReorderSidebar, { props });

        await wrapper.findAll("input[type=checkbox]")[0].setValue(true);
        const submit = new Event("submit", { cancelable: true });
        wrapper.get("form").element.dispatchEvent(submit);

        expect(submit.defaultPrevented).toBe(false);
        expect(wrapper.find("[role=alert]").exists()).toBe(false);
    });

    it("links to the whole order history", () => {
        __setSection("last-ordered-items", { items: [line(1)] });

        const wrapper = mount(ReorderSidebar, { props });

        expect(wrapper.get("a.view-all").attributes("href")).toBe("https://shop.test/sales/order/history/");
    });
});
