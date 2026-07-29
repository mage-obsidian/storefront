import { describe, it, expect, afterEach, vi } from "vitest";
import { lockScroll, unlockScroll } from "./scroll-lock";

const withGutter = (px: number): void => {
    vi.spyOn(window, "innerWidth", "get").mockReturnValue(1440);
    vi.spyOn(document.documentElement, "clientWidth", "get").mockReturnValue(1440 - px);
};

const style = (el: HTMLElement, property: string): string => el.style.getPropertyValue(property);

afterEach(() => {
    vi.restoreAllMocks();
    document.body.removeAttribute("style");
    document.documentElement.removeAttribute("style");
    unlockScroll();
    unlockScroll();
});

describe("scroll lock", () => {
    it("hides the scrollbar, pads the body by the same width and releases the gutter", () => {
        withGutter(15);

        lockScroll();

        expect(style(document.body, "overflow")).toBe("hidden");
        expect(style(document.body, "padding-right")).toBe("15px");
        expect(style(document.documentElement, "scrollbar-gutter")).toBe("auto");
    });

    it("restores every property it touched", () => {
        withGutter(15);

        lockScroll();
        unlockScroll();

        expect(style(document.body, "overflow")).toBe("");
        expect(style(document.body, "padding-right")).toBe("");
        expect(style(document.documentElement, "scrollbar-gutter")).toBe("");
    });

    it("gives back the inline values that were already there", () => {
        document.body.style.setProperty("overflow", "clip");
        document.documentElement.style.setProperty("scrollbar-gutter", "stable");
        withGutter(15);

        lockScroll();
        expect(style(document.documentElement, "scrollbar-gutter")).toBe("auto");

        unlockScroll();
        expect(style(document.body, "overflow")).toBe("clip");
        expect(style(document.documentElement, "scrollbar-gutter")).toBe("stable");
    });

    it("leaves the layout alone when there is no scrollbar to hide", () => {
        withGutter(0);

        lockScroll();

        expect(style(document.body, "overflow")).toBe("hidden");
        expect(style(document.body, "padding-right")).toBe("");
        expect(style(document.documentElement, "scrollbar-gutter")).toBe("");
    });

    // Two drawers can overlap (the mini-cart opening from the mobile menu); the
    // first to close must not hand the page back while the second is still up.
    it("stays locked until the last holder releases it", () => {
        withGutter(15);

        lockScroll();
        lockScroll();
        unlockScroll();

        expect(style(document.body, "overflow")).toBe("hidden");

        unlockScroll();
        expect(style(document.body, "overflow")).toBe("");
    });

    it("ignores a release nobody asked for", () => {
        document.body.style.setProperty("overflow", "visible");

        unlockScroll();

        expect(style(document.body, "overflow")).toBe("visible");
    });
});
