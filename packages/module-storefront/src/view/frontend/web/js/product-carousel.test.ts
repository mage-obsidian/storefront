import { beforeEach, describe, expect, it, vi } from "vitest";
import { enhanceProductCarousel, itemsOf, stepFor, stripOf } from "./product-carousel.ts";

const markup = (count: number, strip = '<ol class="product-items">', item = '<li class="product-item">') =>
    `<div data-content-type="products" data-appearance="carousel">${strip}${Array.from(
        { length: count },
        (_, index) => `${item}<a href="/p${index}">Product ${index}</a></li>`,
    ).join("")}</ol></div>`;

const build = (count = 3, ...rest: string[]): HTMLElement => {
    document.body.innerHTML = markup(count, ...(rest as [string?, string?]));
    return document.querySelector<HTMLElement>('[data-appearance="carousel"]')!;
};

const layout = (root: HTMLElement, width = 200): void => {
    const strip = stripOf(root)!;
    Object.defineProperty(strip, "offsetLeft", { value: 0, configurable: true });
    strip.scrollTo = vi.fn((options) => {
        strip.scrollLeft = (options as ScrollToOptions).left ?? 0;
    }) as unknown as typeof strip.scrollTo;
    itemsOf(strip).forEach((item, index) => {
        Object.defineProperty(item, "offsetLeft", { value: index * width, configurable: true });
    });
};

beforeEach(() => {
    document.body.innerHTML = "";
});

describe("stripOf and itemsOf", () => {
    it("finds the list the author's products are in", () => {
        expect(stripOf(build())?.className).toBe("product-items");
    });

    it("reads the products regardless of which markup rendered them", () => {
        expect(itemsOf(stripOf(build(3))!)).toHaveLength(3);
        expect(
            itemsOf(stripOf(build(2, '<ol data-carousel-strip>', '<li data-carousel-item>'))!),
        ).toHaveLength(2);
    });

    it("does not claim the products of a carousel nested inside another", () => {
        document.body.innerHTML =
            '<ol class="product-items"><li class="product-item">' +
            '<ol class="product-items"><li class="product-item">inner</li></ol>' +
            "</li></ol>";
        const outer = document.querySelector<HTMLElement>(".product-items")!;

        expect(itemsOf(outer)).toHaveLength(1);
    });
});

describe("enhanceProductCarousel", () => {
    it("announces itself as a carousel and each product as a slide", () => {
        const root = build(3);
        layout(root);

        enhanceProductCarousel(root, window);

        expect(root.getAttribute("role")).toBe("group");
        expect(root.getAttribute("aria-roledescription")).toBe("carousel");
        expect(root.getAttribute("aria-label")).toBe("Product carousel");
        expect(
            itemsOf(stripOf(root)!).map((item) => item.getAttribute("aria-label")),
        ).toEqual(["Product 1 of 3", "Product 2 of 3", "Product 3 of 3"]);
    });

    it("keeps the label the author already gave it", () => {
        const root = build(2);
        root.setAttribute("aria-label", "Summer picks");
        layout(root);

        enhanceProductCarousel(root, window);

        expect(root.getAttribute("aria-label")).toBe("Summer picks");
    });

    it("offers a way past it to someone arriving by keyboard", () => {
        const root = build(3);
        layout(root);

        enhanceProductCarousel(root, window);
        const skip = root.querySelector<HTMLAnchorElement>("[data-carousel-skip]")!;

        expect(skip).not.toBeNull();
        expect(root.querySelector(skip.getAttribute("href")!)).not.toBeNull();
        expect(root.firstElementChild).toBe(skip);
    });

    it("leaves every product reachable and traps focus in nothing", () => {
        const root = build(3);
        layout(root);

        enhanceProductCarousel(root, window);

        expect(root.querySelectorAll('[tabindex="-1"]')).toHaveLength(1);
        expect(stripOf(root)!.getAttribute("tabindex")).toBe("0");
        expect(
            Array.from(root.querySelectorAll("a")).filter((a) => a.getAttribute("tabindex") === "-1"),
        ).toHaveLength(0);
    });

    it("walks the strip with the arrow keys", () => {
        const root = build(3);
        layout(root);
        enhanceProductCarousel(root, window);
        const strip = stripOf(root)!;

        strip.dispatchEvent(new KeyboardEvent("keydown", { key: "ArrowRight", bubbles: true }));

        expect(strip.scrollLeft).toBe(200);
    });

    it("stops at the ends instead of wrapping the visitor around", () => {
        const root = build(2);
        layout(root);
        enhanceProductCarousel(root, window);
        const strip = stripOf(root)!;

        strip.dispatchEvent(new KeyboardEvent("keydown", { key: "ArrowLeft", bubbles: true }));
        expect(strip.scrollLeft).toBe(0);

        strip.dispatchEvent(new KeyboardEvent("keydown", { key: "ArrowRight", bubbles: true }));
        strip.dispatchEvent(new KeyboardEvent("keydown", { key: "ArrowRight", bubbles: true }));
        expect(strip.scrollLeft).toBe(200);
    });

    it("leaves a keypress inside a product to the product", () => {
        const root = build(3);
        layout(root);
        enhanceProductCarousel(root, window);
        const strip = stripOf(root)!;

        strip.querySelector("a")!.dispatchEvent(
            new KeyboardEvent("keydown", { key: "ArrowRight", bubbles: true }),
        );

        expect(strip.scrollLeft).toBe(0);
    });

    it("says where the visitor is once they have scrolled", () => {
        const root = build(3);
        layout(root);
        enhanceProductCarousel(root, window);
        const strip = stripOf(root)!;

        strip.scrollLeft = 400;
        strip.dispatchEvent(new Event("scroll"));

        expect(root.querySelector("[data-carousel-status]")?.textContent).toBe("Product 3 of 3");
    });

    it("does nothing at all when the author's carousel has no products", () => {
        const root = build(0);

        expect(enhanceProductCarousel(root, window)).toBe(false);
        expect(root.getAttribute("role")).toBeNull();
        expect(root.querySelector("[data-carousel-skip]")).toBeNull();
    });

    it("does nothing when there is no list to scroll", () => {
        document.body.innerHTML = '<div data-content-type="products" data-appearance="carousel"></div>';
        const root = document.querySelector<HTMLElement>("[data-content-type]")!;

        expect(enhanceProductCarousel(root, window)).toBe(false);
    });

    it("enhances a given carousel only once", () => {
        const root = build(3);
        layout(root);

        expect(enhanceProductCarousel(root, window)).toBe(true);
        expect(enhanceProductCarousel(root, window)).toBe(false);
        expect(root.querySelectorAll("[data-carousel-skip]")).toHaveLength(1);
    });

    it("takes the words it announces from the caller", () => {
        const root = build(2);
        layout(root);

        enhanceProductCarousel(root, window, {
            carousel: "Carrusel",
            skip: "Saltar",
            position: (index, total) => `${index}/${total}`,
        });

        expect(root.getAttribute("aria-label")).toBe("Carrusel");
        expect(root.querySelector("[data-carousel-skip]")?.textContent).toBe("Saltar");
        expect(itemsOf(stripOf(root)!)[0].getAttribute("aria-label")).toBe("1/2");
    });
});

describe("stepFor", () => {
    it("moves one product at a time from wherever the strip is", () => {
        const root = build(4);
        layout(root);
        const strip = stripOf(root)!;
        const items = itemsOf(strip);
        strip.scrollLeft = 400;

        expect(stepFor(strip, items, 1)).toBe(600);
        expect(stepFor(strip, items, -1)).toBe(200);
    });

    it("leaves the strip where it is when there is nothing to step through", () => {
        const root = build(1);
        layout(root);
        const strip = stripOf(root)!;
        strip.scrollLeft = 55;

        expect(stepFor(strip, [], 1)).toBe(55);
    });
});
