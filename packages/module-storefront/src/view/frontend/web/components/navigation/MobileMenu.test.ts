import { describe, it, expect, afterEach } from "vitest";
import { mount } from "@vue/test-utils";
import MobileMenu from "./MobileMenu.vue";

// Above-the-fold mobile navigation island: a hamburger trigger that opens the
// shared Drawer with the server-provided links. The trigger is server-eager
// (the island mounts immediately); the Drawer owns the dialog a11y.
const links = [
    { label: "New in", url: "/new" },
    { label: "Outerwear", url: "/outerwear" },
];

const mountMenu = () => mount(MobileMenu, { props: { links, label: "Menu" }, attachTo: document.body });

afterEach(() => {
    document.body.style.overflow = "";
    document.body.innerHTML = "";
});

describe("MobileMenu", () => {
    it("renders a collapsed trigger and no dialog initially", () => {
        const wrapper = mountMenu();

        const trigger = wrapper.get("button[aria-haspopup='dialog']");
        expect(trigger.attributes("aria-expanded")).toBe("false");
        expect(document.querySelector('[role="dialog"]')).toBeNull();

        wrapper.unmount();
    });

    it("wires the hamburger aria-controls to the dialog id and labels the nav distinctly", async () => {
        const wrapper = mountMenu();
        const trigger = wrapper.get("button[aria-haspopup='dialog']");
        const controls = trigger.attributes("aria-controls");
        expect(controls).toBeTruthy();

        await trigger.trigger("click");

        const dialog = document.querySelector('[role="dialog"]');
        expect(dialog.id).toBe(controls);
        // The dialog is "Menu"; the inner nav must not repeat that label.
        const nav = dialog.querySelector("nav");
        expect(nav.getAttribute("aria-label")).not.toBe("Menu");

        wrapper.unmount();
    });

    it("opens the drawer with the provided links when the trigger is clicked", async () => {
        const wrapper = mountMenu();

        await wrapper.get("button[aria-haspopup='dialog']").trigger("click");

        const dialog = document.querySelector('[role="dialog"]');
        expect(dialog).not.toBeNull();
        expect(wrapper.get("button[aria-haspopup='dialog']").attributes("aria-expanded")).toBe("true");
        expect(dialog.textContent).toContain("New in");
        expect(dialog.querySelector("a[href='/outerwear']")).not.toBeNull();

        wrapper.unmount();
    });

    it("closes when the drawer requests it (Escape)", async () => {
        const wrapper = mountMenu();
        await wrapper.get("button[aria-haspopup='dialog']").trigger("click");

        document.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape" }));
        await wrapper.vm.$nextTick();

        expect(document.querySelector('[role="dialog"]')).toBeNull();
        expect(wrapper.get("button[aria-haspopup='dialog']").attributes("aria-expanded")).toBe("false");

        wrapper.unmount();
    });

    it("renders the utility links (wishlist/compare) with their count islands in the drawer", async () => {
        const wrapper = mount(MobileMenu, {
            props: {
                links,
                label: "Menu",
                utilities: [
                    { url: "/wishlist", label: "My Wish List", kind: "wishlist" },
                    { url: "/catalog/product_compare", label: "Compare Products", kind: "compare" },
                ],
            },
            attachTo: document.body,
        });

        await wrapper.get("button[aria-haspopup='dialog']").trigger("click");

        const dialog = document.querySelector('[role="dialog"]');
        const wishlist = dialog.querySelector("a[href='/wishlist']");
        const compare = dialog.querySelector("a[href='/catalog/product_compare']");
        expect(wishlist).not.toBeNull();
        expect(compare).not.toBeNull();
        expect(wishlist.textContent).toContain("My Wish List");
        // The reactive count island mounts alongside the label.
        expect(wishlist.querySelector("svg")).not.toBeNull();
        expect(compare.querySelector("svg")).not.toBeNull();

        wrapper.unmount();
    });

    it("renders nested categories as an accordion inside the drawer", async () => {
        const wrapper = mount(MobileMenu, {
            props: {
                links: [
                    { label: "New in", url: "/new" },
                    {
                        label: "Outerwear",
                        url: "/outerwear",
                        children: [{ label: "Coats", url: "/outerwear/coats" }],
                    },
                ],
                label: "Menu",
            },
            attachTo: document.body,
        });

        await wrapper.get("button[aria-haspopup='dialog']").trigger("click");
        const dialog = document.querySelector('[role="dialog"]');

        // Top-level categories are links; the branch also gets a toggle button.
        expect(dialog.querySelector("a[href='/new']")).not.toBeNull();
        expect(dialog.querySelector("a[href='/outerwear']")).not.toBeNull();
        const toggle = dialog.querySelector("button[aria-label='Toggle Outerwear submenu']");
        expect(toggle).not.toBeNull();
        expect(toggle.getAttribute("aria-expanded")).toBe("false");

        toggle.dispatchEvent(new MouseEvent("click", { bubbles: true }));
        await wrapper.vm.$nextTick();
        expect(toggle.getAttribute("aria-expanded")).toBe("true");
        expect(dialog.querySelector("a[href='/outerwear/coats']")).not.toBeNull();

        wrapper.unmount();
    });

    it("omits the utility section when no utilities are provided", async () => {
        const wrapper = mountMenu();

        await wrapper.get("button[aria-haspopup='dialog']").trigger("click");

        const dialog = document.querySelector('[role="dialog"]');
        expect(dialog.querySelector("a[href='/wishlist']")).toBeNull();

        wrapper.unmount();
    });

    it("renders the store/currency switchers inline inside the drawer when provided", async () => {
        const wrapper = mount(MobileMenu, {
            props: {
                links,
                label: "Menu",
                currencies: {
                    label: "USD",
                    srLabel: "Change currency",
                    items: [
                        { label: "USD", url: "/c/USD", current: true },
                        { label: "EUR", url: "/c/EUR", current: false },
                    ],
                },
            },
            attachTo: document.body,
        });

        await wrapper.get("button[aria-haspopup='dialog']").trigger("click");

        const dialog = document.querySelector('[role="dialog"]');
        expect(dialog.querySelector("a[href='/c/EUR']")).not.toBeNull();
        // Inline variant: options are shown directly, with no dropdown toggle.
        expect(dialog.querySelector("button[aria-haspopup='menu']")).toBeNull();

        wrapper.unmount();
    });

    it("defaults the drawer wordmark to OBSIDIAN and renders it as plain text", async () => {
        const wrapper = mountMenu();
        await wrapper.get("button[aria-haspopup='dialog']").trigger("click");

        const dialog = document.querySelector('[role="dialog"]');
        expect(dialog.textContent).toContain("OBSIDIAN");
        // No homeUrl → the wordmark is a span, not a link.
        expect(dialog.querySelector("span.font-display").textContent).toBe("OBSIDIAN");

        wrapper.unmount();
    });

    it("lets a child theme override the brand and links it to homeUrl", async () => {
        const wrapper = mount(MobileMenu, {
            props: { links, label: "Menu", brand: "MOTO", homeUrl: "/" },
            attachTo: document.body,
        });
        await wrapper.get("button[aria-haspopup='dialog']").trigger("click");

        const dialog = document.querySelector('[role="dialog"]');
        const wordmark = dialog.querySelector("a.font-display");
        expect(wordmark).not.toBeNull();
        expect(wordmark.getAttribute("href")).toBe("/");
        expect(wordmark.textContent).toBe("MOTO");
        expect(dialog.textContent).not.toContain("OBSIDIAN");

        wrapper.unmount();
    });

    it("omits the switcher section when there is only one option", async () => {
        const wrapper = mount(MobileMenu, {
            props: {
                links,
                label: "Menu",
                currencies: { label: "USD", srLabel: "Change currency", items: [{ label: "USD", url: "/c/USD", current: true }] },
            },
            attachTo: document.body,
        });

        await wrapper.get("button[aria-haspopup='dialog']").trigger("click");

        const dialog = document.querySelector('[role="dialog"]');
        expect(dialog.querySelector("a[href='/c/USD']")).toBeNull();

        wrapper.unmount();
    });
});
