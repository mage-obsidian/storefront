import { describe, it, expect, afterEach } from "vitest";
import { mount } from "@vue/test-utils";
import Toast from "./Toast.vue";

// Global toast host: announces `obsidian:toast` window events in an aria-live
// region. Reused by add-to-cart now and wishlist/compare later.
afterEach(() => {
    document.body.innerHTML = "";
});

const fire = (detail) => window.dispatchEvent(new CustomEvent("obsidian:toast", { detail }));

describe("Toast", () => {
    it("announces a toast message in a live region", async () => {
        const wrapper = mount(Toast, { attachTo: document.body });

        fire({ message: "Added to cart", tone: "success" });
        await wrapper.vm.$nextTick();

        const live = wrapper.get('[role="status"]');
        expect(live.attributes("aria-live")).toBe("polite");
        expect(wrapper.text()).toContain("Added to cart");

        wrapper.unmount();
    });

    it("ignores events without a message", async () => {
        const wrapper = mount(Toast, { attachTo: document.body });

        fire({ tone: "success" });
        await wrapper.vm.$nextTick();

        expect(wrapper.findAll(".pointer-events-auto")).toHaveLength(0);

        wrapper.unmount();
    });
});
