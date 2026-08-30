import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import { mount } from "@vue/test-utils";
import Toast from "./Toast.vue";
import { notify, NotificationTone, onNotification } from "MageObsidian_Storefront::js/notifications";

// Global toast host: announces `obsidian:toast` window events in an aria-live
// region. Reused by add-to-cart now and wishlist/compare later.
beforeEach(() => {
    onNotification(() => {})();
});

afterEach(() => {
    document.body.innerHTML = "";
    vi.useRealTimers();
});

const DURATION = 3200;

const fire = (detail) => window.dispatchEvent(new CustomEvent("obsidian:toast", { detail }));

const rows = (wrapper) => wrapper.findAll(".pointer-events-auto");

describe("Toast", () => {
    it("anchors the stack to the bottom, clear of the header", async () => {
        const wrapper = mount(Toast, { attachTo: document.body });

        const host = wrapper.get(".toast-host");
        expect(host.classes()).toContain("bottom-0");
        expect(host.classes()).not.toContain("top-0");

        wrapper.unmount();
    });

    it("centres a toast on narrow viewports and corners it from sm up", async () => {
        const wrapper = mount(Toast, { attachTo: document.body });

        fire({ message: "Added to cart", tone: "success" });
        await wrapper.vm.$nextTick();

        const row = wrapper.get(".pointer-events-auto").element.parentElement;
        expect(row.classList.contains("justify-center")).toBe(true);
        expect(row.classList.contains("sm:justify-end")).toBe(true);

        wrapper.unmount();
    });

    it("renders the assertive region nearest the anchored corner", async () => {
        const wrapper = mount(Toast, { attachTo: document.body });

        const roles = wrapper.findAll("[aria-live]").map((r) => r.attributes("role"));
        expect(roles).toEqual(["status", "alert"]);

        wrapper.unmount();
    });

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
        expect(wrapper.find(".obsidian-toast .bg-sale").exists()).toBe(true);

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
    it("gives each Magento tone its own colour", async () => {
        const wrapper = mount(Toast, { attachTo: document.body });

        await notify("Nope", NotificationTone.Error);
        await notify("Heads up", NotificationTone.Notice);
        await wrapper.vm.$nextTick();

        const error = wrapper.get('[role="alert"]');
        const notice = wrapper.get('[role="status"]');
        expect(error.find(".bg-danger").exists()).toBe(true);
        expect(error.find(".text-danger").exists()).toBe(true);
        expect(notice.find(".bg-ash-400").exists()).toBe(true);
        expect(notice.find(".text-ash-500").exists()).toBe(true);

        wrapper.unmount();
    });

    it("announces a notice politely and an error assertively", async () => {
        const wrapper = mount(Toast, { attachTo: document.body });

        await notify("Heads up", NotificationTone.Notice);
        await notify("Nope", NotificationTone.Error);
        await wrapper.vm.$nextTick();

        expect(wrapper.get('[role="status"]').text()).toContain("Heads up");
        expect(wrapper.get('[role="alert"]').text()).toContain("Nope");

        wrapper.unmount();
    });

    it("honours an explicit duration", async () => {
        vi.useFakeTimers();
        const wrapper = mount(Toast, { attachTo: document.body });

        await notify("Added to cart", NotificationTone.Success, { durationMs: 9000 });
        await wrapper.vm.$nextTick();

        vi.advanceTimersByTime(DURATION);
        await wrapper.vm.$nextTick();
        expect(rows(wrapper)).toHaveLength(1);

        vi.advanceTimersByTime(9000 - DURATION);
        await wrapper.vm.$nextTick();
        expect(rows(wrapper)).toHaveLength(0);

        wrapper.unmount();
    });

    it("keeps a long message up longer than the default", async () => {
        vi.useFakeTimers();
        const wrapper = mount(Toast, { attachTo: document.body });

        await notify("Thank you for registering with Main Website Store.");
        await wrapper.vm.$nextTick();

        vi.advanceTimersByTime(DURATION);
        await wrapper.vm.$nextTick();

        expect(rows(wrapper)).toHaveLength(1);

        wrapper.unmount();
    });

    it("renders sanitised markup only when the notification asks for it", async () => {
        const wrapper = mount(Toast, { attachTo: document.body });

        await notify('Please <a href="/confirm">confirm</a>.', NotificationTone.Success, {
            html: true,
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.find("a[href='/confirm']").exists()).toBe(true);

        wrapper.unmount();
    });

    it("shows markup as literal text when the notification is plain", async () => {
        const wrapper = mount(Toast, { attachTo: document.body });

        await notify("<b>not markup</b>");
        await wrapper.vm.$nextTick();

        expect(wrapper.find("b").exists()).toBe(false);
        expect(wrapper.text()).toContain("<b>not markup</b>");

        wrapper.unmount();
    });

    it("caps the stack so a backlog cannot bury the page", async () => {
        const wrapper = mount(Toast, { attachTo: document.body });

        for (const n of [1, 2, 3, 4, 5, 6]) {
            await notify(`Message ${n}`);
        }
        await wrapper.vm.$nextTick();

        expect(rows(wrapper)).toHaveLength(4);
        expect(wrapper.text()).not.toContain("Message 1");
        expect(wrapper.text()).toContain("Message 6");

        wrapper.unmount();
    });
});
