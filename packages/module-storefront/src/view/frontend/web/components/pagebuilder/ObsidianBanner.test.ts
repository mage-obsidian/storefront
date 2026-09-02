import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import ObsidianBanner from "./ObsidianBanner.vue";

const enhanceReveal = vi.fn();
vi.mock("MageObsidian_Storefront::js/reveal-on-interaction", () => ({
    enhanceReveal: (...args: unknown[]) => enhanceReveal(...args),
}));

const props = {
    heading: "Autumn",
    message: "The new drop",
    buttonLabel: "Shop",
    buttonUrl: "/autumn",
    overlayColor: "rgb(0, 0, 0)",
};

beforeEach(() => {
    enhanceReveal.mockClear();
    document.body.innerHTML = "";
});

describe("ObsidianBanner", () => {
    it("writes what the author configured", () => {
        const wrapper = mount(ObsidianBanner, { props });

        expect(wrapper.text()).toContain("Autumn");
        expect(wrapper.text()).toContain("The new drop");
        expect(wrapper.find("a").attributes("href")).toBe("/autumn");
        expect(wrapper.find(".pagebuilder-overlay").attributes("data-overlay-color")).toBe("rgb(0, 0, 0)");
    });

    it("leaves the revealing to the behaviour module when the author asked for it", () => {
        const wrapper = mount(ObsidianBanner, {
            props: { ...props, reveal: "hover" },
            attachTo: document.body,
        });

        expect(enhanceReveal).toHaveBeenCalledTimes(1);
        expect(enhanceReveal.mock.calls[0][0]).toBe(wrapper.element);
        expect(wrapper.attributes("data-show-button")).toBe("hover");
        expect(wrapper.attributes("data-show-overlay")).toBe("hover");
    });

    it("binds nothing at all when the author wants it always visible", () => {
        const wrapper = mount(ObsidianBanner, { props, attachTo: document.body });

        expect(enhanceReveal).not.toHaveBeenCalled();
        expect(wrapper.attributes("data-show-button")).toBe("always");
    });

    it("omits the button when the author gave it no label", () => {
        const wrapper = mount(ObsidianBanner, { props: { heading: "Autumn" } });

        expect(wrapper.find("a").exists()).toBe(false);
    });

    it("keeps the class names the storefront stylesheet and the behaviour both key on", () => {
        const wrapper = mount(ObsidianBanner, { props });

        expect(wrapper.find(".pagebuilder-banner-wrapper").exists()).toBe(true);
        expect(wrapper.find(".pagebuilder-overlay").exists()).toBe(true);
        expect(wrapper.find(".pagebuilder-banner-button").exists()).toBe(true);
    });
});
