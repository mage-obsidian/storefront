import { describe, it, expect, afterEach, vi } from "vitest";
import { mount } from "@vue/test-utils";
import Toast from "./Toast.vue";
import { notify, NotificationTone } from "MageObsidian_Storefront::js/notifications";

// Global toast host: announces `obsidian:toast` window events in an aria-live
// region. Reused by add-to-cart now and wishlist/compare later.
afterEach(() => {
    document.body.innerHTML = "";
    vi.useRealTimers();
});

const DURATION = 3200;

const fire = (detail) => window.dispatchEvent(new CustomEvent("obsidian:toast", { detail }));

const rows = (wrapper) => wrapper.findAll(".pointer-events-auto");

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

    it("shows a notification announced on the bus", async () => {
        const wrapper = mount(Toast, { attachTo: document.body });

        await notify("Removed from bag", NotificationTone.Warning);
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain("Removed from bag");
        expect(wrapper.get(".pointer-events-auto").classes()).toContain("text-sale");

        wrapper.unmount();
    });

    it("unsubscribes from the bus once unmounted", async () => {
        const wrapper = mount(Toast, { attachTo: document.body });
        wrapper.unmount();

        const survivor = mount(Toast, { attachTo: document.body });
        await notify("Only once");
        await survivor.vm.$nextTick();

        expect(rows(survivor)).toHaveLength(1);

        survivor.unmount();
    });

    it("ignores events without a message", async () => {
        const wrapper = mount(Toast, { attachTo: document.body });

        fire({ tone: "success" });
        await wrapper.vm.$nextTick();

        expect(rows(wrapper)).toHaveLength(0);

        wrapper.unmount();
    });

    it("routes errors to the assertive region and keeps the rest polite", async () => {
        const wrapper = mount(Toast, { attachTo: document.body });

        await notify("Could not add to cart", NotificationTone.Error);
        await notify("Added to cart");
        await wrapper.vm.$nextTick();

        const alert = wrapper.get('[role="alert"]');
        const status = wrapper.get('[role="status"]');
        expect(alert.attributes("aria-live")).toBe("assertive");
        expect(alert.text()).toContain("Could not add to cart");
        expect(status.text()).toContain("Added to cart");
        expect(status.text()).not.toContain("Could not add to cart");

        wrapper.unmount();
    });

    it("dismisses a toast when its close button is clicked", async () => {
        const wrapper = mount(Toast, { attachTo: document.body });

        await notify("Added to cart");
        await wrapper.vm.$nextTick();

        await wrapper.get("button[aria-label]").trigger("click");

        expect(rows(wrapper)).toHaveLength(0);

        wrapper.unmount();
    });

    it("labels the close button with the phrase passed from the template", async () => {
        const wrapper = mount(Toast, {
            attachTo: document.body,
            props: { labels: { dismiss: "Descartar" } },
        });

        await notify("Added to cart");
        await wrapper.vm.$nextTick();

        expect(wrapper.get("button").attributes("aria-label")).toBe("Descartar");

        wrapper.unmount();
    });

    it("holds the toast while the pointer is over it and releases it after", async () => {
        vi.useFakeTimers();
        const wrapper = mount(Toast, { attachTo: document.body });

        await notify("Added to cart");
        await wrapper.vm.$nextTick();

        const row = wrapper.get(".pointer-events-auto");
        await row.trigger("mouseenter");
        vi.advanceTimersByTime(DURATION * 2);
        await wrapper.vm.$nextTick();

        expect(rows(wrapper)).toHaveLength(1);

        await row.trigger("mouseleave");
        vi.advanceTimersByTime(DURATION);
        await wrapper.vm.$nextTick();

        expect(rows(wrapper)).toHaveLength(0);

        wrapper.unmount();
    });

    it("holds the toast while it holds focus", async () => {
        vi.useFakeTimers();
        const wrapper = mount(Toast, { attachTo: document.body });

        await notify("Added to cart");
        await wrapper.vm.$nextTick();

        const row = wrapper.get(".pointer-events-auto");
        await row.trigger("focusin");
        vi.advanceTimersByTime(DURATION * 2);
        await wrapper.vm.$nextTick();

        expect(rows(wrapper)).toHaveLength(1);

        await row.trigger("focusout");
        vi.advanceTimersByTime(DURATION);
        await wrapper.vm.$nextTick();

        expect(rows(wrapper)).toHaveLength(0);

        wrapper.unmount();
    });

    it("keeps only the remaining time after a pause, not a fresh countdown", async () => {
        vi.useFakeTimers();
        const wrapper = mount(Toast, { attachTo: document.body });

        await notify("Added to cart");
        await wrapper.vm.$nextTick();

        const row = wrapper.get(".pointer-events-auto");
        vi.advanceTimersByTime(DURATION - 200);
        await row.trigger("mouseenter");
        vi.advanceTimersByTime(DURATION);
        await row.trigger("mouseleave");

        vi.advanceTimersByTime(200);
        await wrapper.vm.$nextTick();

        expect(rows(wrapper)).toHaveLength(0);

        wrapper.unmount();
    });

    it("does not leave later toasts immortal after a hover with no matching leave", async () => {
        vi.useFakeTimers();
        const wrapper = mount(Toast, { attachTo: document.body });

        await notify("Added to cart");
        await wrapper.vm.$nextTick();
        await wrapper.get(".pointer-events-auto").trigger("mouseenter");
        await wrapper.get("button[aria-label]").trigger("click");

        await notify("Removed from bag", NotificationTone.Warning);
        await wrapper.vm.$nextTick();
        vi.advanceTimersByTime(DURATION);
        await wrapper.vm.$nextTick();

        expect(rows(wrapper)).toHaveLength(0);

        wrapper.unmount();
    });

    it("releases the survivors when the hovered toast is closed", async () => {
        vi.useFakeTimers();
        const wrapper = mount(Toast, { attachTo: document.body });

        await notify("Added to cart");
        await notify("Removed from bag", NotificationTone.Warning);
        await wrapper.vm.$nextTick();

        const [first, second] = rows(wrapper);
        await first.trigger("mouseenter");
        await first.get("button[aria-label]").trigger("click");
        expect(second.exists()).toBe(true);

        vi.advanceTimersByTime(DURATION);
        await wrapper.vm.$nextTick();

        expect(rows(wrapper)).toHaveLength(0);

        wrapper.unmount();
    });

    it("cancels pending timers on unmount", async () => {
        vi.useFakeTimers();
        const wrapper = mount(Toast, { attachTo: document.body });
        const idle = vi.getTimerCount();

        await notify("Added to cart");
        await notify("Removed from bag", NotificationTone.Warning);
        await wrapper.vm.$nextTick();
        expect(vi.getTimerCount()).toBe(idle + 2);

        wrapper.unmount();

        expect(vi.getTimerCount()).toBe(idle);
    });
});
