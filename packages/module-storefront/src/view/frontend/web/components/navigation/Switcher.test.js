import { describe, it, expect, afterEach } from "vitest";
import { mount } from "@vue/test-utils";
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
