import { beforeEach, describe, expect, it, vi } from "vitest";
import {
    enhanceReveal,
    enhanceReveals,
    overlayColorOf,
    revealablesOf,
    setRevealed,
    supportsHover,
} from "./reveal-on-interaction.ts";

const banner = (attributes = 'data-show-overlay="hover" data-show-button="hover"'): HTMLElement => {
    document.body.innerHTML = `
        <div data-content-type="banner" ${attributes}>
            <div class="pagebuilder-overlay" data-overlay-color="rgb(0, 0, 0)">
                <div class="pagebuilder-poster-content"><p>Copy</p></div>
                <button type="button" class="pagebuilder-banner-button">Shop</button>
            </div>
        </div>`;
    return document.querySelector<HTMLElement>('[data-content-type="banner"]')!;
};

const view = (hover: boolean): Window =>
    ({ matchMedia: vi.fn(() => ({ matches: !hover })) }) as unknown as Window;

beforeEach(() => {
    document.body.innerHTML = "";
});

describe("supportsHover", () => {
    it("believes a screen that reports a pointer", () => {
        expect(supportsHover(view(true))).toBe(true);
    });

    it("believes a screen that reports none", () => {
        expect(supportsHover(view(false))).toBe(false);
    });

    it("assumes a pointer when the browser cannot say", () => {
        expect(supportsHover({} as Window)).toBe(true);
    });
});

describe("revealablesOf", () => {
    it("takes only what the author asked to be revealed", () => {
        expect(revealablesOf(banner('data-show-overlay="hover"')).button).toBeNull();
        expect(revealablesOf(banner('data-show-button="hover"')).overlay).toBeNull();
        expect(revealablesOf(banner('data-show-overlay="always"')).overlay).toBeNull();
    });

    it("reads the colour the author chose for the overlay", () => {
        expect(overlayColorOf(banner())).toBe("rgb(0, 0, 0)");
    });

    it("falls back to transparent when the author set no colour", () => {
        document.body.innerHTML = '<div data-show-overlay="hover"><div class="pagebuilder-overlay"></div></div>';
        expect(overlayColorOf(document.querySelector("[data-show-overlay]")!)).toBe("transparent");
    });
});

describe("enhanceReveal", () => {
    it("starts concealed on a screen with a pointer", () => {
        const root = banner();

        enhanceReveal(root, view(true));

        expect(root.getAttribute("data-revealed")).toBe("false");
        expect(root.querySelector<HTMLElement>(".pagebuilder-banner-button")!.style.opacity).toBe("0");
    });

    it("reveals on hover and conceals again on leaving", () => {
        const root = banner();
        enhanceReveal(root, view(true));

        root.dispatchEvent(new Event("mouseenter"));
        expect(root.getAttribute("data-revealed")).toBe("true");
        expect(root.querySelector<HTMLElement>(".pagebuilder-overlay")!.style.backgroundColor).toBe(
            "rgb(0, 0, 0)",
        );

        root.dispatchEvent(new Event("mouseleave"));
        expect(root.getAttribute("data-revealed")).toBe("false");
    });

    it("reveals when the visitor arrives with the keyboard", () => {
        const root = banner();
        enhanceReveal(root, view(true));

        root.querySelector<HTMLButtonElement>(".pagebuilder-banner-button")!.dispatchEvent(
            new FocusEvent("focusin", { bubbles: true }),
        );

        expect(root.getAttribute("data-revealed")).toBe("true");
    });

    it("stays revealed while the focus is still somewhere inside", () => {
        const root = banner();
        enhanceReveal(root, view(true));
        root.dispatchEvent(new FocusEvent("focusin", { bubbles: true }));

        const inside = root.querySelector(".pagebuilder-poster-content")!;
        root.dispatchEvent(new FocusEvent("focusout", { bubbles: true, relatedTarget: inside }));

        expect(root.getAttribute("data-revealed")).toBe("true");
    });

    it("conceals again once the focus has left it", () => {
        const root = banner();
        enhanceReveal(root, view(true));
        root.dispatchEvent(new FocusEvent("focusin", { bubbles: true }));

        root.dispatchEvent(new FocusEvent("focusout", { bubbles: true, relatedTarget: document.body }));

        expect(root.getAttribute("data-revealed")).toBe("false");
    });

    it("never conceals on a screen with no pointer", () => {
        const root = banner();

        enhanceReveal(root, view(false));

        expect(root.getAttribute("data-revealed")).toBe("true");
        root.dispatchEvent(new Event("mouseleave"));
        expect(root.getAttribute("data-revealed")).toBe("true");
    });

    it("leaves the concealed button in the tab order, so it can be reached at all", () => {
        const root = banner();

        enhanceReveal(root, view(true));
        const button = root.querySelector<HTMLElement>(".pagebuilder-banner-button")!;

        expect(button.style.visibility).toBe("");
        expect(button.hasAttribute("hidden")).toBe(false);
        expect(button.getAttribute("tabindex")).toBeNull();
    });

    it("does nothing to a banner the author did not ask to reveal", () => {
        const root = banner('data-show-overlay="always" data-show-button="always"');

        expect(enhanceReveal(root, view(true))).toBe(false);
        expect(root.getAttribute("data-revealed")).toBeNull();
    });

    it("binds a given banner only once", () => {
        const root = banner();

        expect(enhanceReveal(root, view(true))).toBe(true);
        expect(enhanceReveal(root, view(true))).toBe(false);
    });
});

describe("setRevealed", () => {
    it("is the whole of the state, so a caller can drive it directly", () => {
        const root = banner();

        setRevealed(root, true);
        expect(root.querySelector<HTMLElement>(".pagebuilder-banner-button")!.style.opacity).toBe("1");

        setRevealed(root, false);
        expect(root.querySelector<HTMLElement>(".pagebuilder-overlay")!.style.backgroundColor).toBe(
            "transparent",
        );
    });
});

describe("enhanceReveals", () => {
    it("finds every banner and slide the author asked to reveal", () => {
        document.body.innerHTML =
            '<div data-content-type="banner" data-show-button="hover"><button class="pagebuilder-banner-button"></button></div>' +
            '<div data-content-type="slide" data-show-overlay="hover"><div class="pagebuilder-overlay" data-overlay-color="red"></div></div>' +
            '<div data-content-type="banner" data-show-button="never"><button class="pagebuilder-banner-button"></button></div>';

        enhanceReveals(document, view(true));

        expect(document.querySelectorAll("[data-revealed]")).toHaveLength(2);
    });
});
