import { describe, it, expect, afterEach } from "vitest";
import { mount } from "@vue/test-utils";
import Drawer from "./Drawer.vue";

// Shared off-canvas drawer used by the mobile menu now and the mini-cart later.
// Contract: a11y dialog semantics, close on Escape / backdrop, and body
// scroll-lock while open. Focus management is asserted at the panel level.
const mountOpen = (props = {}, slots = {}) =>
    mount(Drawer, {
        props: { open: true, label: "Main menu", ...props },
        slots: { default: "<a href='#one'>One</a>", ...slots },
        attachTo: document.body,
    });

afterEach(() => {
    document.body.style.overflow = "";
    document.body.innerHTML = "";
});

describe("Drawer", () => {
    it("renders no dialog while closed", () => {
        const wrapper = mount(Drawer, { props: { open: false, label: "Menu" }, attachTo: document.body });

        expect(document.querySelector('[role="dialog"]')).toBeNull();

        wrapper.unmount();
    });

    it("exposes an accessible dialog with the slotted content when open", () => {
        const wrapper = mountOpen();

        const dialog = document.querySelector('[role="dialog"]');
        expect(dialog).not.toBeNull();
        expect(dialog.getAttribute("aria-modal")).toBe("true");
        expect(dialog.getAttribute("aria-label")).toBe("Main menu");
        expect(document.body.textContent).toContain("One");

        wrapper.unmount();
    });

    it("emits close on Escape", async () => {
        const wrapper = mountOpen();

        document.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape" }));
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted("close")).toBeTruthy();

        wrapper.unmount();
    });

    it("emits close when the backdrop is clicked", async () => {
        const wrapper = mountOpen();

        const backdrop = document.querySelector("[data-drawer-backdrop]");
        expect(backdrop).not.toBeNull();
        backdrop.dispatchEvent(new MouseEvent("click", { bubbles: true }));
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted("close")).toBeTruthy();

        wrapper.unmount();
    });

    it("does not emit close when the panel itself is clicked", async () => {
        const wrapper = mountOpen();

        document.querySelector('[role="dialog"]').dispatchEvent(new MouseEvent("click", { bubbles: true }));
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted("close")).toBeFalsy();

        wrapper.unmount();
    });

    it("locks body scroll while open and restores it on close", async () => {
        const wrapper = mount(Drawer, { props: { open: false, label: "Menu" }, attachTo: document.body });
        expect(document.body.style.overflow).not.toBe("hidden");

        await wrapper.setProps({ open: true });
        expect(document.body.style.overflow).toBe("hidden");

        await wrapper.setProps({ open: false });
        expect(document.body.style.overflow).not.toBe("hidden");

        wrapper.unmount();
    });

    it("moves focus to the first focusable element when opened", async () => {
        const wrapper = mount(Drawer, {
            props: { open: false, label: "Menu" },
            slots: { default: "<button id='first-btn'>First</button><a href='#last'>Last</a>" },
            attachTo: document.body,
        });

        await wrapper.setProps({ open: true });
        await new Promise((r) => setTimeout(r, 0));

        expect(document.activeElement).toBe(document.getElementById("first-btn"));

        wrapper.unmount();
    });

    it("traps Tab focus within the dialog (last wraps to first)", async () => {
        const wrapper = mountOpen({}, { default: "<button id='a'>A</button><a id='b' href='#b'>B</a>" });
        await new Promise((r) => setTimeout(r, 0));

        document.getElementById("b").focus();
        document.dispatchEvent(new KeyboardEvent("keydown", { key: "Tab" }));
        await wrapper.vm.$nextTick();

        expect(document.activeElement).toBe(document.getElementById("a"));

        wrapper.unmount();
    });

    it("traps Shift+Tab focus within the dialog (first wraps to last)", async () => {
        const wrapper = mountOpen({}, { default: "<button id='a'>A</button><a id='b' href='#b'>B</a>" });
        await new Promise((r) => setTimeout(r, 0));

        document.getElementById("a").focus();
        document.dispatchEvent(new KeyboardEvent("keydown", { key: "Tab", shiftKey: true }));
        await wrapper.vm.$nextTick();

        expect(document.activeElement).toBe(document.getElementById("b"));

        wrapper.unmount();
    });

    it("links the dialog by id (prop, with a default fallback)", () => {
        const wrapper = mountOpen({ id: "drawer-test" });
        expect(document.querySelector('[role="dialog"]').id).toBe("drawer-test");
        wrapper.unmount();

        const auto = mountOpen();
        expect(document.querySelector('[role="dialog"]').id).toBeTruthy();
        auto.unmount();
    });
});
