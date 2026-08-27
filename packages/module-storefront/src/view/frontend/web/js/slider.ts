/**
 * The carousel primitive the templates share.
 *
 * A scroll-snapping strip rather than a library: the browser already does the
 * scrolling, the momentum and the touch handling, so what is left is the part it
 * does not do — a control per slide, arrows, and optional autoplay. It degrades
 * to a horizontally scrollable list when this never loads, which is why the
 * markup carries no state of its own.
 *
 * A template opts in with `data-slider` on the scrolling element and
 * `data-slider-slide` on each child; everything else is optional.
 */
export interface SliderOptions {
    dots: boolean;
    arrows: boolean;
    autoplay: boolean;
    interval: number;
    labels: { previous: string; next: string; slide: string };
}

const DEFAULTS: SliderOptions = {
    dots: true,
    arrows: false,
    autoplay: false,
    interval: 5000,
    labels: { previous: "Previous", next: "Next", slide: "Slide" },
};

const bound = new WeakSet<Element>();

export const slidesOf = (slider: Element, selector = "[data-slider-slide]"): HTMLElement[] =>
    Array.from(slider.querySelectorAll<HTMLElement>(selector)).filter(
        (slide) => slide.parentElement === slider || slide.closest("[data-slider]") === slider,
    );

export function goToSlide(slider: HTMLElement, index: number, selector?: string): number {
    const slides = slidesOf(slider, selector);
    if (slides.length === 0) {
        return -1;
    }

    const chosen = ((index % slides.length) + slides.length) % slides.length;
    slider.scrollTo({ left: slides[chosen].offsetLeft - slider.offsetLeft, behavior: "smooth" });
    slider.parentElement
        ?.querySelectorAll<HTMLButtonElement>("[data-slider-dot]")
        .forEach((dot, position) => dot.setAttribute("aria-current", String(position === chosen)));
    slider.dataset.sliderCurrent = String(chosen);

    return chosen;
}

const control = (doc: Document, className: string, label: string, marker: string): HTMLButtonElement => {
    const button = doc.createElement("button");
    button.type = "button";
    button.className = className;
    button.setAttribute("aria-label", label);
    button.setAttribute(marker, "");
    return button;
};

const optionsFrom = (slider: HTMLElement, overrides: Partial<SliderOptions>): SliderOptions => ({
    ...DEFAULTS,
    dots: slider.getAttribute("data-slider-dots") !== "false",
    arrows: slider.getAttribute("data-slider-arrows") === "true",
    autoplay: slider.getAttribute("data-slider-autoplay") === "true",
    interval: Number.parseInt(slider.getAttribute("data-slider-interval") ?? "", 10) || DEFAULTS.interval,
    ...overrides,
});

/**
 * @returns the slides it took over, so a caller can tell an enhanced strip from
 * one that was left alone.
 */
export function enhanceSlider(
    slider: HTMLElement,
    overrides: Partial<SliderOptions> = {},
    view: Window = window,
    selector?: string,
): HTMLElement[] {
    const slides = slidesOf(slider, selector);
    if (slides.length < 2 || bound.has(slider)) {
        return [];
    }
    bound.add(slider);

    const options = optionsFrom(slider, overrides);
    const doc = slider.ownerDocument;

    if (options.arrows) {
        const previous = control(doc, "slider__arrow slider__arrow--previous", options.labels.previous, "data-slider-previous");
        const next = control(doc, "slider__arrow slider__arrow--next", options.labels.next, "data-slider-next");
        previous.addEventListener("click", () => goToSlide(slider, Number(slider.dataset.sliderCurrent ?? 0) - 1, selector));
        next.addEventListener("click", () => goToSlide(slider, Number(slider.dataset.sliderCurrent ?? 0) + 1, selector));
        slider.insertAdjacentElement("beforebegin", previous);
        slider.insertAdjacentElement("afterend", next);
    }

    if (options.dots) {
        const dots = doc.createElement("div");
        dots.className = "slider__dots";
        slides.forEach((_slide, index) => {
            const dot = control(doc, "slider__dot", `${options.labels.slide} ${index + 1}`, "data-slider-dot");
            dot.setAttribute("aria-current", String(index === 0));
            dot.addEventListener("click", () => goToSlide(slider, index, selector));
            dots.appendChild(dot);
        });
        slider.insertAdjacentElement("afterend", dots);
    }

    slider.dataset.sliderCurrent = "0";

    if (options.autoplay) {
        view.setInterval(() => {
            goToSlide(slider, Number(slider.dataset.sliderCurrent ?? 0) + 1, selector);
        }, options.interval);
    }

    return slides;
}

export function bindSliders(root: ParentNode = document, view: Window = window): void {
    root.querySelectorAll<HTMLElement>("[data-slider]").forEach((slider) => enhanceSlider(slider, {}, view));
}

bindSliders();
