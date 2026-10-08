// This file is part of the MageObsidian - Storefront project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import WishlistSidebar from "./WishlistSidebar.vue";
import { __reset, __setSection } from "MageObsidian_ModernFrontend::js/customer-data";
import { getFormKey } from "MageObsidian_Storefront::js/form-key-provider";

const labels = {
    title: "My Wish List",
    open: "Go to Wish List",
    addToCart: "Add to Cart",
    remove: "Remove %1 from wish list",
};

const post = (action: string, data: Record<string, unknown>) => JSON.stringify({ action, data });

const entry = (id: number, overrides: Record<string, unknown> = {}) => ({
    image: { src: `https://shop.test/media/${id}.jpg`, width: 80, height: 80, alt: `Item ${id}` },
    product_id: id,
    product_name: `Item ${id}`,
    product_url: `https://shop.test/item-${id}.html`,
    product_price: `<span class="price">$${id}.00</span>`,
    product_is_saleable_and_visible: true,
    product_has_required_options: false,
    add_to_cart_params: post(`https://shop.test/wishlist/index/cart/`, { item: id, uenc: "u1" }),
    delete_item_params: post(`https://shop.test/wishlist/index/remove/`, { item: id, uenc: "u2" }),
    ...overrides,
});

const props = { labels, listUrl: "https://shop.test/wishlist/" };

beforeEach(() => {
    __reset();
});

describe("WishlistSidebar", () => {
    it("renders nothing for a shopper with an empty list", () => {
        __setSection("wishlist", { counter: null, items: [] });

        const wrapper = mount(WishlistSidebar, { props });

        expect(wrapper.find("[data-wishlist-sidebar]").exists()).toBe(false);
    });

    it("lists each saved item with its name, link and price", () => {
        __setSection("wishlist", { counter: "2 items", items: [entry(1), entry(2)] });

        const wrapper = mount(WishlistSidebar, { props });

        const rows = wrapper.findAll("li");
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain("Item 1");
        expect(rows[0].text()).toContain("$1.00");
        expect(rows[0].findAll("a").some((link) => link.attributes("href") === "https://shop.test/item-1.html")).toBe(true);
        expect(wrapper.text()).toContain("2 items");
    });

    it("leads to the full list", () => {
        __setSection("wishlist", { counter: "1 item", items: [entry(1)] });

        const wrapper = mount(WishlistSidebar, { props });

        expect(wrapper.get("a[href='https://shop.test/wishlist/']").text()).toBe("Go to Wish List");
    });

    it("moves an item to the bag with the post data the section carries", () => {
        __setSection("wishlist", { counter: "1 item", items: [entry(7)] });

        const wrapper = mount(WishlistSidebar, { props });

        const form = wrapper.get("form[data-wishlist-add]");
        expect(form.attributes("action")).toBe("https://shop.test/wishlist/index/cart/");
        expect(form.attributes("method")).toBe("post");
        expect((form.get("input[name=item]").element as HTMLInputElement).value).toBe("7");
        expect((form.get("input[name=uenc]").element as HTMLInputElement).value).toBe("u1");
        expect((form.get("input[name=form_key]").element as HTMLInputElement).value).toBe(getFormKey());
    });

    it("offers no bag button for an item that cannot be bought", () => {
        __setSection("wishlist", {
            counter: "1 item",
            items: [entry(3, { product_is_saleable_and_visible: false })],
        });

        const wrapper = mount(WishlistSidebar, { props });

        expect(wrapper.find("form[data-wishlist-add]").exists()).toBe(false);
    });

    it("removes an item with a post that names it", () => {
        __setSection("wishlist", { counter: "1 item", items: [entry(5)] });

        const wrapper = mount(WishlistSidebar, { props });

        const form = wrapper.get("form[data-wishlist-remove]");
        expect(form.attributes("action")).toBe("https://shop.test/wishlist/index/remove/");
        expect(form.get("button").attributes("aria-label")).toBe("Remove Item 5 from wish list");
    });

    it("survives post data it cannot read", () => {
        __setSection("wishlist", {
            counter: "1 item",
            items: [entry(9, { add_to_cart_params: "not json", delete_item_params: "" })],
        });

        const wrapper = mount(WishlistSidebar, { props });

        expect(wrapper.findAll("li")).toHaveLength(1);
        expect(wrapper.find("form").exists()).toBe(false);
    });
});
