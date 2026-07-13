import { describe, it, expect, afterEach, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import PrimaryNav from "./PrimaryNav.vue";

// Desktop primary nav island: renders the category bar and collapses whatever
// does not fit into a "More" disclosure. The overflow math is unit-tested in
// overflowNav.test.ts; here we cover rendering, the a11y wiring, and the
// disclosure — forcing overflow by stubbing element metrics (happy-dom reports
// 0 for layout sizes, so nothing would overflow otherwise).
const links = [
    { label: "MOTOR", url: "/motor" },
    { label: "FRENOS", url: "/frenos", active: true },
    { label: "FILTROS", url: "/filtros" },
];

afterEach(() => {
    document.body.innerHTML = "";
});

describe("PrimaryNav — everything fits", () => {
    it("renders every link, flags the active one, and hides the More trigger", async () => {
        const wrapper = mount(PrimaryNav, {
            props: { links, label: "Primary", moreLabel: "Más" },
            attachTo: document.body,
        });
        await flushPromises();

        const items = wrapper.findAll("[data-nav-item]");
        expect(items).toHaveLength(3);
        expect(items[0].attributes("href")).toBe("/motor");
        expect(items[0].text()).toBe("MOTOR");
        expect(wrapper.get("nav").attributes("aria-label")).toBe("Primary");
        expect(wrapper.get("a[href='/frenos']").attributes("aria-current")).toBe("page");

        // Layout sizes are 0 in happy-dom → all fit → More stays hidden.
        expect(wrapper.get("button").isVisible()).toBe(false);

        // Steady state must not clip the x axis, or absolute dropdowns/flyouts
        // wider than the bar would be cut off; the clip only guards the measuring
        // pass. (BUG 2)
        const navClass = wrapper.get("nav").classes();
        expect(navClass).toContain("overflow-x-visible");
        expect(navClass).not.toContain("overflow-x-clip");

        wrapper.unmount();
    });
});

describe("PrimaryNav — overflow into the More disclosure", () => {
    let offsetWidthDesc: PropertyDescriptor | undefined;
    let clientWidthDesc: PropertyDescriptor | undefined;

    beforeEach(() => {
        offsetWidthDesc = Object.getOwnPropertyDescriptor(HTMLElement.prototype, "offsetWidth");
        clientWidthDesc = Object.getOwnPropertyDescriptor(HTMLElement.prototype, "clientWidth");
        // Every item (and the More trigger) is 120px wide; the bar is only 100px,
        // so not even one item + More fits → all links land in the dropdown.
        Object.defineProperty(HTMLElement.prototype, "offsetWidth", { configurable: true, get: () => 120 });
        Object.defineProperty(HTMLElement.prototype, "clientWidth", { configurable: true, get: () => 100 });
    });

    afterEach(() => {
        if (offsetWidthDesc) {
            Object.defineProperty(HTMLElement.prototype, "offsetWidth", offsetWidthDesc);
        } else {
            delete (HTMLElement.prototype as unknown as Record<string, unknown>).offsetWidth;
        }
        if (clientWidthDesc) {
            Object.defineProperty(HTMLElement.prototype, "clientWidth", clientWidthDesc);
        } else {
            delete (HTMLElement.prototype as unknown as Record<string, unknown>).clientWidth;
        }
    });

    it("shows the More trigger and lists the overflow links, with disclosure a11y", async () => {
        const wrapper = mount(PrimaryNav, {
            props: { links, moreLabel: "Más" },
            attachTo: document.body,
        });
        await flushPromises();

        const trigger = wrapper.get("button");
        expect(trigger.isVisible()).toBe(true);
        expect(trigger.text()).toContain("Más");
        expect(trigger.attributes("aria-haspopup")).toBe("true");
        expect(trigger.attributes("aria-expanded")).toBe("false");

        await trigger.trigger("click");
        expect(trigger.attributes("aria-expanded")).toBe("true");

        const panel = document.getElementById(trigger.attributes("aria-controls") as string);
        expect(panel).not.toBeNull();
        expect(panel!.querySelectorAll("a")).toHaveLength(3);
        // A disclosure, not an ARIA menu widget.
        expect(panel!.querySelector("[role='menuitem']")).toBeNull();

        await trigger.trigger("keydown", { key: "Escape" });
        expect(trigger.attributes("aria-expanded")).toBe("false");
        expect(document.activeElement).toBe(trigger.element);

        wrapper.unmount();
    });
});

describe("PrimaryNav — subcategory flyouts", () => {
    const withChildren = [
        {
            label: "MOTOR",
            url: "/motor",
            children: [
                { label: "OIL", url: "/motor/oil" },
                { label: "BELTS", url: "/motor/belts" },
            ],
        },
        { label: "FRENOS", url: "/frenos" },
    ];

    it("renders a parent with a submenu affordance and reveals its children on hover", async () => {
        const wrapper = mount(PrimaryNav, { props: { links: withChildren }, attachTo: document.body });
        await flushPromises();

        const parent = wrapper.get("a[href='/motor']");
        expect(parent.attributes("aria-haspopup")).toBe("true");
        expect(parent.attributes("aria-expanded")).toBe("false");
        // A plain link stays a plain link.
        expect(wrapper.get("a[href='/frenos']").attributes("aria-haspopup")).toBeUndefined();

        const flyout = parent.element.closest("[data-nav-item]") as HTMLElement;
        await wrapper.get("[data-nav-item]:first-child").trigger("mouseenter");
        expect(parent.attributes("aria-expanded")).toBe("true");
        expect(flyout.querySelector("a[href='/motor/oil']")).not.toBeNull();

        await wrapper.get("[data-nav-item]:first-child").trigger("mouseleave");
        expect(parent.attributes("aria-expanded")).toBe("false");

        wrapper.unmount();
    });

    it("opens on keyboard focus and closes on Escape", async () => {
        const wrapper = mount(PrimaryNav, { props: { links: withChildren }, attachTo: document.body });
        await flushPromises();

        const parentWrap = wrapper.get("[data-nav-item]:first-child");
        await parentWrap.trigger("focusin");
        expect(wrapper.get("a[href='/motor']").attributes("aria-expanded")).toBe("true");

        await parentWrap.trigger("keydown", { key: "Escape" });
        expect(wrapper.get("a[href='/motor']").attributes("aria-expanded")).toBe("false");

        wrapper.unmount();
    });
});
