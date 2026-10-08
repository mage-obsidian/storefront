// This file is part of the MageObsidian - Storefront project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect, beforeEach, vi } from "vitest";
import { flushPromises, mount } from "@vue/test-utils";
import CompareSidebar from "./CompareSidebar.vue";
import { __reset, __setReloadResponse, __setSection, reload } from "MageObsidian_ModernFrontend::js/customer-data";

const labels = {
    title: "Compare Products",
    open: "Compare",
    remove: "Remove %1 from compare list",
};

const removal = (id: number) =>
    JSON.stringify({ action: `https://shop.test/catalog/product_compare/remove/product/${id}`, data: { product: id } });

const items = [
    { id: 11, name: "Joust Duffle Bag", product_url: "https://shop.test/joust.html", remove_url: removal(11) },
    { id: 12, name: "Crown Summit &amp; Pack", product_url: "https://shop.test/crown.html", remove_url: removal(12) },
];

beforeEach(() => {
    __reset();
    document.cookie = "form_key=abc123";
    vi.stubGlobal("fetch", vi.fn(async () => ({ ok: true })));
});

describe("CompareSidebar", () => {
    it("renders nothing while nothing is being compared", () => {
        const wrapper = mount(CompareSidebar, { props: { labels } });

        expect(wrapper.find("[data-compare-sidebar]").exists()).toBe(false);
    });

    it("lists the compared products with a link to each", () => {
        __setSection("compare-products", { items, listUrl: "https://shop.test/catalog/product_compare/" });

        const wrapper = mount(CompareSidebar, { props: { labels } });

        const rows = wrapper.findAll("li");
        expect(rows).toHaveLength(2);
        expect(rows[0].get("a").attributes("href")).toBe("https://shop.test/joust.html");
        expect(rows[1].get("a").text()).toBe("Crown Summit & Pack");
    });

    it("leads to the comparison from the configured address", () => {
        __setSection("compare-products", { items, listUrl: "https://shop.test/catalog/product_compare/" });

        const wrapper = mount(CompareSidebar, { props: { labels } });

        const open = wrapper.get("a.btn");
        expect(open.text()).toBe("Compare");
        expect(open.attributes("href")).toBe("https://shop.test/catalog/product_compare/");
    });

    it("names the product in each remove control", () => {
        __setSection("compare-products", { items, listUrl: "/c" });

        const wrapper = mount(CompareSidebar, { props: { labels } });

        expect(wrapper.findAll("button")[0].attributes("aria-label")).toBe("Remove Joust Duffle Bag from compare list");
    });

    it("removes a product through the section and reloads it", async () => {
        __setSection("compare-products", { items, listUrl: "/c" });
        __setReloadResponse({ "compare-products": { items: [items[1]], listUrl: "/c" } });
        const wrapper = mount(CompareSidebar, { props: { labels } });

        await wrapper.findAll("button")[0].trigger("click");
        await flushPromises();

        expect(fetch).toHaveBeenCalledWith(
            "https://shop.test/catalog/product_compare/remove/product/11",
            expect.objectContaining({ method: "POST" }),
        );
        expect(reload.calls).toHaveLength(1);
        expect(wrapper.findAll("li")).toHaveLength(1);
    });
});
