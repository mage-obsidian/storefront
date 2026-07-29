import { describe, it, expect, afterEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import PrimaryNav from "./PrimaryNav.vue";

const links = [
    { label: "MOTOR", url: "/motor" },
    { label: "FRENOS", url: "/frenos", active: true },
    { label: "FILTROS", url: "/filtros" },
];

afterEach(() => {
    document.body.innerHTML = "";
});

describe("PrimaryNav — bar rendering", () => {
    it("renders every link with the index the overflow rules address", async () => {
        const wrapper = mount(PrimaryNav, {
            props: { links, label: "Primary", moreLabel: "Más" },
            attachTo: document.body,
        });
        await flushPromises();

        const items = wrapper.findAll("[data-nav-item]");
        expect(items).toHaveLength(3);
        expect(items.map((item) => item.attributes("data-nav-index"))).toEqual(["0", "1", "2"]);
        expect(items[0].attributes("href")).toBe("/motor");
        expect(items[0].text()).toBe("MOTOR");
        expect(wrapper.get("nav").attributes("aria-label")).toBe("Primary");
        expect(wrapper.get("a[href='/frenos']").attributes("aria-current")).toBe("page");

        wrapper.unmount();
    });

    it("leaves the split entirely to CSS: no inline display, no clipping toggle", async () => {
        const wrapper = mount(PrimaryNav, { props: { links, moreLabel: "Más" }, attachTo: document.body });
        await flushPromises();

        for (const item of wrapper.findAll("[data-nav-item]")) {
            expect((item.element as HTMLElement).style.display).toBe("");
        }

        const trigger = wrapper.get("[data-nav-more]");
        expect((trigger.element as HTMLElement).style.display).toBe("");
        expect(trigger.classes()).toContain("relative");
        expect(trigger.classes()).not.toContain("invisible");
        expect(trigger.classes()).not.toContain("absolute");

        const navClass = wrapper.get("nav").classes();
        expect(navClass).not.toContain("overflow-x-clip");
        expect(navClass).not.toContain("overflow-x-visible");

        wrapper.unmount();
    });
});

describe("PrimaryNav — the More disclosure", () => {
    it("lists every link in the panel so the rules can hide the ones already in the bar", async () => {
        const wrapper = mount(PrimaryNav, { props: { links, moreLabel: "Más" }, attachTo: document.body });
        await flushPromises();

        const trigger = wrapper.get("button");
        expect(trigger.text()).toContain("Más");
        expect(trigger.attributes("aria-haspopup")).toBe("true");
        expect(trigger.attributes("aria-expanded")).toBe("false");

        await trigger.trigger("click");
        expect(trigger.attributes("aria-expanded")).toBe("true");

        const panel = document.getElementById(trigger.attributes("aria-controls") as string);
        expect(panel).not.toBeNull();
        const entries = panel!.querySelectorAll("[data-nav-overflow-index]");
        expect([...entries].map((entry) => entry.getAttribute("data-nav-overflow-index"))).toEqual(["0", "1", "2"]);
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
        expect(wrapper.get("a[href='/frenos']").attributes("aria-haspopup")).toBeUndefined();

        const flyout = parent.element.closest("[data-nav-item]") as HTMLElement;
        await wrapper.get("[data-nav-item]:first-child").trigger("mouseenter");
        expect(parent.attributes("aria-expanded")).toBe("true");
        expect(flyout.querySelector("a[href='/motor/oil']")).not.toBeNull();

        await wrapper.get("[data-nav-item]:first-child").trigger("mouseleave");
        expect(parent.attributes("aria-expanded")).toBe("false");

        wrapper.unmount();
    });

    it("anchors the flyout flush to its trigger, leaving no dead gap to cross", async () => {
        const wrapper = mount(PrimaryNav, { props: { links: withChildren }, attachTo: document.body });
        await flushPromises();

        const item = wrapper.get("[data-nav-item]:first-child");
        await item.trigger("mouseenter");

        const child = item.element.querySelector("a[href='/motor/oil']") as HTMLElement;
        const panel = child.closest(".absolute") as HTMLElement | null;
        expect(panel).not.toBeNull();
        expect(item.element.contains(panel)).toBe(true);

        // The pointer travels from the trigger down into the panel. A top margin
        // would sit outside both boxes, so crossing it fires mouseleave on the
        // wrapper and closes the flyout before it can be clicked; top-full plus
        // inner padding keeps the travel path inside the wrapper's subtree.
        const classes = [...panel!.classList];
        expect(classes).toContain("top-full");
        expect(classes.filter((name) => name.startsWith("mt-"))).toEqual([]);

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
