import { beforeEach, describe, expect, it, vi } from "vitest";
import { enhanceParallax, prefersReducedMotion, shiftFor, speedOf } from "./parallax.ts";

const build = (attributes = 'data-enable-parallax="1" data-parallax-speed="0.4"'): HTMLElement => {
    document.body.innerHTML = `<div ${attributes} style="background-image: url(hero.jpg)"><p>Row</p></div>`;
    return document.querySelector("[data-enable-parallax]") as HTMLElement;
};

const viewWith = (reduced: boolean): Window => {
    const view = {
        document,
        innerHeight: 800,
        matchMedia: vi.fn(() => ({ matches: reduced })),
        getComputedStyle: (element: Element) => window.getComputedStyle(element),
        requestAnimationFrame: vi.fn((callback: FrameRequestCallback) => {
            callback(0);
            return 1;
        }),
        cancelAnimationFrame: vi.fn(),
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        IntersectionObserver: undefined,
    };

    return view as unknown as Window;
};

beforeEach(() => {
    document.body.innerHTML = "";
});

describe("speedOf", () => {
    it("reads the speed the author chose", () => {
        expect(speedOf(build('data-enable-parallax="1" data-parallax-speed="0.8"'))).toBe(0.8);
    });

    it("falls back when the author left it out", () => {
        expect(speedOf(build('data-enable-parallax="1"'))).toBe(0.3);
    });

    it("falls back when the value is out of range", () => {
        expect(speedOf(build('data-enable-parallax="1" data-parallax-speed="7"'))).toBe(0.3);
        expect(speedOf(build('data-enable-parallax="1" data-parallax-speed="-1"'))).toBe(0.3);
    });
});

describe("shiftFor", () => {
    it("is centred when the element is centred in the viewport", () => {
        expect(shiftFor(0, 800, 800, 0.5)).toBeCloseTo(0, 5);
    });

    it("moves the opposite way above and below the middle", () => {
        expect(shiftFor(800, 400, 800, 0.5)).toBeLessThan(0);
        expect(shiftFor(-400, 400, 800, 0.5)).toBeGreaterThan(0);
    });

    it("stops travelling once the element has left the viewport", () => {
        expect(shiftFor(-10_000, 400, 800, 0.5)).toBe(shiftFor(-20_000, 400, 800, 0.5));
    });
});

describe("prefersReducedMotion", () => {
    it("reports what the visitor declared", () => {
        expect(prefersReducedMotion(viewWith(true))).toBe(true);
        expect(prefersReducedMotion(viewWith(false))).toBe(false);
    });

    it("is false when the browser cannot answer", () => {
        expect(prefersReducedMotion({} as Window)).toBe(false);
    });
});

describe("enhanceParallax", () => {
    it("installs nothing when the visitor prefers reduced motion", () => {
        const row = build();

        enhanceParallax(document, viewWith(true));

        expect(row.querySelector("[data-parallax-layer]")).toBeNull();
        expect(row.style.backgroundImage).toContain("hero.jpg");
    });

    it("moves the background onto a layer it can transform", () => {
        const row = build();

        enhanceParallax(document, viewWith(false));

        const layer = row.querySelector("[data-parallax-layer]") as HTMLElement;
        expect(layer).not.toBeNull();
        expect(layer.style.backgroundImage).toContain("hero.jpg");
        expect(row.style.backgroundImage).toBe("none");
        expect(layer.getAttribute("aria-hidden")).toBe("true");
    });

    it("leaves a row with no background alone", () => {
        document.body.innerHTML = '<div data-enable-parallax="1"><p>Row</p></div>';
        const row = document.querySelector("[data-enable-parallax]") as HTMLElement;

        enhanceParallax(document, viewWith(false));

        expect(row.querySelector("[data-parallax-layer]")).toBeNull();
    });

    it("does not install twice on the same row", () => {
        const row = build();
        const view = viewWith(false);

        enhanceParallax(document, view);
        enhanceParallax(document, view);

        expect(row.querySelectorAll("[data-parallax-layer]")).toHaveLength(1);
    });

    it("ignores a row the author did not ask for", () => {
        document.body.innerHTML = '<div data-enable-parallax="0" style="background-image: url(hero.jpg)"></div>';

        enhanceParallax(document, viewWith(false));

        expect(document.querySelector("[data-parallax-layer]")).toBeNull();
    });
});
