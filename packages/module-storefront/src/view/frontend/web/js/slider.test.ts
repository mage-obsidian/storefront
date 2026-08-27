import { beforeEach, describe, expect, it, vi } from "vitest";
import { bindSliders, enhanceSlider, goToSlide, slidesOf } from "./slider.ts";

const build = (attributes = "", slides = 3): HTMLElement => {
    document.body.innerHTML = `
        <div class="carousel">
            <div class="slider" data-slider ${attributes}>
                ${Array.from({ length: slides }, (_v, index) => `<div data-slider-slide><p>Slide ${index + 1}</p></div>`).join("")}
            </div>
        </div>`;
    const slider = document.querySelector("[data-slider]") as HTMLElement;
    slider.scrollTo = vi.fn();
    return slider;
};

const dots = (): HTMLButtonElement[] => Array.from(document.querySelectorAll("[data-slider-dot]"));

beforeEach(() => {
    document.body.innerHTML = "";
});

describe("enhanceSlider", () => {
    it("adds one control per slide", () => {
        const slider = build();

        expect(enhanceSlider(slider)).toHaveLength(3);
        expect(dots()).toHaveLength(3);
        expect(dots()[0].getAttribute("aria-current")).toBe("true");
    });

    // The strip already scrolls with a finger and a keyboard; controls are what
    // a pointer is missing, and an author can decline them.
    it("declines the controls when asked to", () => {
        enhanceSlider(build('data-slider-dots="false"'));

        expect(dots()).toHaveLength(0);
    });

    it("adds arrows only where they were asked for", () => {
        enhanceSlider(build('data-slider-arrows="true"'));

        expect(document.querySelectorAll("[data-slider-previous], [data-slider-next]")).toHaveLength(2);
    });

    // One slide is not a carousel, and controls for it would be noise.
    it("leaves a single slide alone", () => {
        const slider = build("", 1);

        expect(enhanceSlider(slider)).toEqual([]);
        expect(dots()).toHaveLength(0);
    });

    it("takes a strip over once", () => {
        const slider = build();

        enhanceSlider(slider);
        enhanceSlider(slider);

        expect(dots()).toHaveLength(3);
    });

    it("advances on its own when asked", () => {
        vi.useFakeTimers();
        const slider = build('data-slider-autoplay="true" data-slider-interval="1000"');

        enhanceSlider(slider, {}, window);
        vi.advanceTimersByTime(1000);

        expect(slider.scrollTo).toHaveBeenCalled();
        expect(slider.dataset.sliderCurrent).toBe("1");
        vi.useRealTimers();
    });

    /**
     * The reason this is a primitive rather than one slider: Page Builder's
     * slides carry the platform's own marker, and the caller passes it in
     * instead of the markup being changed to suit.
     */
    it("takes the caller's own slide marker", () => {
        document.body.innerHTML = `
            <div data-slider>
                <div data-content-type="slide"><p>One</p></div>
                <div data-content-type="slide"><p>Two</p></div>
            </div>`;
        const slider = document.querySelector("[data-slider]") as HTMLElement;
        slider.scrollTo = vi.fn();

        const taken = enhanceSlider(slider, {}, window, '[data-content-type="slide"]');

        expect(taken).toHaveLength(2);
        expect(dots()).toHaveLength(2);
    });
});

describe("goToSlide", () => {
    it("moves to a slide and marks its control", () => {
        const slider = build();
        enhanceSlider(slider);

        expect(goToSlide(slider, 2)).toBe(2);
        expect(dots()[2].getAttribute("aria-current")).toBe("true");
        expect(dots()[0].getAttribute("aria-current")).toBe("false");
    });

    it("wraps at either end", () => {
        const slider = build();
        enhanceSlider(slider);

        expect(goToSlide(slider, 3)).toBe(0);
        expect(goToSlide(slider, -1)).toBe(2);
    });

    it("says nothing happened when there are no slides", () => {
        document.body.innerHTML = '<div data-slider></div>';

        expect(goToSlide(document.querySelector("[data-slider]") as HTMLElement, 0)).toBe(-1);
    });
});

describe("slidesOf", () => {
    // A slider inside a slider must not count its neighbour's slides.
    it("counts only its own slides", () => {
        document.body.innerHTML = `
            <div data-slider id="outer">
                <div data-slider-slide><p>Outer</p></div>
                <div data-slider-slide>
                    <div data-slider id="inner">
                        <div data-slider-slide><p>Inner</p></div>
                    </div>
                </div>
            </div>`;

        expect(slidesOf(document.querySelector("#inner") as HTMLElement)).toHaveLength(1);
    });
});

describe("bindSliders", () => {
    it("takes over every strip that opted in", () => {
        build();

        bindSliders(document, window);

        expect(dots()).toHaveLength(3);
    });
});
