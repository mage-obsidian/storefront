import { describe, it, expect, afterEach, beforeEach, vi } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import Switcher from "./Switcher.vue";

// Reusable store/language/currency switcher. The dropdown is a DISCLOSURE (a
// button that toggles a list of navigation links), not an ARIA menu — the items
// are links, so Tab moves through them naturally and Escape returns focus to the
// trigger. The inline variant is a flat list, used inside the mobile drawer.
const items = [
    { label: "USD", url: "/switch?currency=USD", current: true },
    { label: "EUR", url: "/switch?currency=EUR", current: false },
];

afterEach(() => {
    document.body.innerHTML = "";
});

describe("Switcher (disclosure dropdown)", () => {
    const mountDropdown = () =>
        mount(Switcher, {
            props: { label: "USD", srLabel: "Change currency", items },
            attachTo: document.body,
        });

    it("renders a collapsed trigger wired to the panel, no menu role", () => {
        const wrapper = mountDropdown();

        const trigger = wrapper.get("button");
        expect(trigger.attributes("aria-expanded")).toBe("false");
        expect(trigger.text()).toContain("USD");
        // Disclosure, not a menu widget.
        expect(wrapper.find("[role='menu']").exists()).toBe(false);
        // Panel hidden while collapsed.
        const panelId = trigger.attributes("aria-controls");
        expect(panelId).toBeTruthy();
        expect(document.getElementById(panelId)).toBeNull();

        wrapper.unmount();
    });

    it("gives the trigger an accessible name that contains its visible text (WCAG 2.5.3)", () => {
        const wrapper = mountDropdown();

        const trigger = wrapper.get("button");
        // Visible text is the current value ("USD"); the accessible name must
        // contain it, not just the generic "Change currency".
        expect(trigger.attributes("aria-label")).toContain("USD");

        wrapper.unmount();
    });

    it("opens the panel with link options and flags the current one", async () => {
        const wrapper = mountDropdown();

        await wrapper.get("button").trigger("click");

        const trigger = wrapper.get("button");
        expect(trigger.attributes("aria-expanded")).toBe("true");
        const panel = document.getElementById(trigger.attributes("aria-controls"));
        expect(panel).not.toBeNull();
        expect(panel.querySelector("a[href='/switch?currency=EUR']").textContent).toContain("EUR");
        expect(panel.querySelector("[aria-current='true']").textContent).toContain("USD");
        expect(panel.querySelector("[role='menuitem']")).toBeNull();

        wrapper.unmount();
    });

    it("moves focus to the first link when opened", async () => {
        const wrapper = mountDropdown();

        await wrapper.get("button").trigger("click");
        await wrapper.vm.$nextTick();

        const firstLink = document.querySelector("a[href='/switch?currency=USD']");
        expect(document.activeElement).toBe(firstLink);

        wrapper.unmount();
    });

    it("closes on Escape and returns focus to the trigger", async () => {
        const wrapper = mountDropdown();
        const trigger = wrapper.get("button");
        await trigger.trigger("click");

        await trigger.trigger("keydown", { key: "Escape" });

        expect(trigger.attributes("aria-expanded")).toBe("false");
        expect(document.activeElement).toBe(trigger.element);

        wrapper.unmount();
    });
});

describe("Switcher (inline)", () => {
    it("renders every option without a toggle and flags the current one", () => {
        const wrapper = mount(Switcher, {
            props: { label: "USD", srLabel: "Change currency", items, variant: "inline" },
        });

        expect(wrapper.find("button").exists()).toBe(false);
        expect(wrapper.findAll("a")).toHaveLength(2);
        expect(wrapper.get("[aria-current='true']").text()).toContain("USD");
    });
});

// A native switch redirects back to a cacheable page; with built-in FPC the
// browser can serve the stale page from its own HTTP cache, so the click is
// intercepted to apply the switch and force a revalidating navigation.
describe("Switcher (FPC-safe revalidation)", () => {
    let reload: ReturnType<typeof vi.fn>;
    let assign: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        reload = vi.fn();
        assign = vi.fn();
        vi.spyOn(window.location, "reload").mockImplementation(reload);
        vi.spyOn(window.location, "assign").mockImplementation(assign);
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });

    const mountInline = () =>
        mount(Switcher, {
            props: { label: "USD", srLabel: "Change currency", items, variant: "inline" },
            attachTo: document.body,
        });

    it("applies the switch via fetch and reloads when the target is the current page", async () => {
        vi.stubGlobal("fetch", vi.fn(() => Promise.resolve({ url: window.location.href })));
        const wrapper = mountInline();

        await wrapper.get("a[href='/switch?currency=EUR']").trigger("click");
        await flushPromises();

        expect(fetch).toHaveBeenCalledWith("/switch?currency=EUR", {
            credentials: "same-origin",
            redirect: "follow",
        });
        expect(reload).toHaveBeenCalled();
        expect(assign).not.toHaveBeenCalled();

        wrapper.unmount();
    });

    it("navigates to the resolved URL when the switch redirects elsewhere", async () => {
        vi.stubGlobal("fetch", vi.fn(() => Promise.resolve({ url: "https://shop.test/es/" })));
        const wrapper = mountInline();

        await wrapper.get("a[href='/switch?currency=EUR']").trigger("click");
        await flushPromises();

        expect(assign).toHaveBeenCalledWith("https://shop.test/es/");
        expect(reload).not.toHaveBeenCalled();

        wrapper.unmount();
    });

    it("falls back to native navigation when the fetch fails", async () => {
        vi.stubGlobal("fetch", vi.fn(() => Promise.reject(new Error("network"))));
        const wrapper = mountInline();

        await wrapper.get("a[href='/switch?currency=EUR']").trigger("click");
        await flushPromises();

        expect(assign).toHaveBeenCalledWith("/switch?currency=EUR");

        wrapper.unmount();
    });

    it("leaves modified clicks to the browser (open in a new tab)", async () => {
        const fetchSpy = vi.fn(() => Promise.resolve({ url: window.location.href }));
        vi.stubGlobal("fetch", fetchSpy);
        const wrapper = mountInline();

        await wrapper.get("a[href='/switch?currency=EUR']").trigger("click", { metaKey: true });
        await flushPromises();

        expect(fetchSpy).not.toHaveBeenCalled();
        expect(reload).not.toHaveBeenCalled();
        expect(assign).not.toHaveBeenCalled();

        wrapper.unmount();
    });
});
