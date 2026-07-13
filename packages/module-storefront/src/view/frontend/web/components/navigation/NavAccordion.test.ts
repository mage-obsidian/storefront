import { describe, it, expect, afterEach } from "vitest";
import { mount } from "@vue/test-utils";
import NavAccordion from "./NavAccordion.vue";

// Recursive mobile nav tree: flat items are plain links; a branch is a category
// link plus a toggle button that expands its children, and the component recurses
// for deeper levels.
const tree = [
    { label: "New in", url: "/new" },
    {
        label: "Outerwear",
        url: "/outerwear",
        children: [
            { label: "Coats", url: "/outerwear/coats" },
            {
                label: "Jackets",
                url: "/outerwear/jackets",
                children: [{ label: "Bombers", url: "/outerwear/jackets/bombers" }],
            },
        ],
    },
];

afterEach(() => {
    document.body.innerHTML = "";
});

describe("NavAccordion", () => {
    it("renders flat items as plain links with no toggle", () => {
        const wrapper = mount(NavAccordion, { props: { items: [{ label: "New in", url: "/new" }] } });

        expect(wrapper.get("a[href='/new']").text()).toBe("New in");
        expect(wrapper.find("button").exists()).toBe(false);
    });

    it("keeps a branch collapsed until its toggle is pressed", async () => {
        const wrapper = mount(NavAccordion, { props: { items: tree }, attachTo: document.body });

        // The parent is a real link even while collapsed.
        expect(wrapper.get("a[href='/outerwear']").exists()).toBe(true);
        const toggle = wrapper.get("button[aria-label='Toggle Outerwear submenu']");
        expect(toggle.attributes("aria-expanded")).toBe("false");
        expect(wrapper.find("a[href='/outerwear/coats']").isVisible()).toBe(false);

        await toggle.trigger("click");
        expect(toggle.attributes("aria-expanded")).toBe("true");
        expect(wrapper.get("a[href='/outerwear/coats']").isVisible()).toBe(true);

        wrapper.unmount();
    });

    it("recurses so a deeper branch has its own toggle", async () => {
        const wrapper = mount(NavAccordion, { props: { items: tree }, attachTo: document.body });

        await wrapper.get("button[aria-label='Toggle Outerwear submenu']").trigger("click");
        const nested = wrapper.get("button[aria-label='Toggle Jackets submenu']");
        expect(wrapper.find("a[href='/outerwear/jackets/bombers']").isVisible()).toBe(false);

        await nested.trigger("click");
        expect(wrapper.get("a[href='/outerwear/jackets/bombers']").isVisible()).toBe(true);

        wrapper.unmount();
    });
});
