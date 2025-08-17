import { describe, it, expect, beforeEach } from "vitest";
import { init } from "./gallery.js";

// Gallery enhancer: thumbs swap the main image and the strip reacts to the
// configurable island's `obsidian:variant-image` event (single-image swap, full
// strip rebuild, or reset). happy-dom has no startViewTransition, so swaps apply
// synchronously.

function setup() {
    document.body.innerHTML = `
        <div data-pdp>
            <img data-gallery-main src="/a.jpg" alt="A">
            <ul data-gallery-thumbs data-thumb-label="Show image %1">
                <li><button data-gallery-thumb data-large="/a.jpg" data-label="A" aria-pressed="true"><img></button></li>
                <li><button data-gallery-thumb data-large="/b.jpg" data-label="B" aria-pressed="false"><img></button></li>
            </ul>
        </div>`;
    init();
}

const variantTiles = [
    { large: "/red-main.jpg", thumb: "/red-t1.jpg", label: "Red" },
    { large: "/red-back.jpg", thumb: "/red-t2.jpg", label: "Red back" },
    { large: "/red-side.jpg", thumb: "/red-t3.jpg", label: "Red side" },
];

function fireVariant(detail) {
    window.dispatchEvent(new CustomEvent("obsidian:variant-image", { detail }));
}

describe("gallery enhancer", () => {
    beforeEach(setup);

    it("swaps the main image and moves the pressed state on thumb click", () => {
        const thumbs = document.querySelectorAll("[data-gallery-thumb]");
        thumbs[1].click();

        const main = document.querySelector("[data-gallery-main]");
        expect(main.getAttribute("src")).toBe("/b.jpg");
        expect(main.getAttribute("alt")).toBe("B");
        expect(thumbs[0].getAttribute("aria-pressed")).toBe("false");
        expect(thumbs[1].getAttribute("aria-pressed")).toBe("true");
    });

    it("swaps only the main image for a single-image variant event", () => {
        fireVariant({ large: "/c.jpg", label: "C" });

        const main = document.querySelector("[data-gallery-main]");
        expect(main.getAttribute("src")).toBe("/c.jpg");
        expect(main.getAttribute("alt")).toBe("C");
    });

    it("rebuilds the whole thumbnail strip when a variant carries tiles", () => {
        fireVariant({ large: "/red-main.jpg", label: "Red", tiles: variantTiles });

        const main = document.querySelector("[data-gallery-main]");
        expect(main.getAttribute("src")).toBe("/red-main.jpg");
        expect(main.getAttribute("alt")).toBe("Red");

        const thumbs = document.querySelectorAll("[data-gallery-thumb]");
        expect(thumbs).toHaveLength(3);
        expect(thumbs[0].getAttribute("aria-pressed")).toBe("true");
        expect([...thumbs].map((t) => t.dataset.large)).toEqual([
            "/red-main.jpg",
            "/red-back.jpg",
            "/red-side.jpg",
        ]);
        expect(thumbs[2].getAttribute("aria-label")).toBe("Show image 3");
    });

    it("keeps rebuilt thumbs interactive (delegated listeners)", () => {
        fireVariant({ large: "/red-main.jpg", label: "Red", tiles: variantTiles });
        document.querySelectorAll("[data-gallery-thumb]")[2].click();

        const main = document.querySelector("[data-gallery-main]");
        expect(main.getAttribute("src")).toBe("/red-side.jpg");
        expect(main.getAttribute("alt")).toBe("Red side");
    });

    it("keeps the prior alt when a variant image has no caption", () => {
        fireVariant({ large: "/red-main.jpg", label: "", tiles: [{ large: "/red-main.jpg", thumb: "/t.jpg", label: "" }] });

        const main = document.querySelector("[data-gallery-main]");
        expect(main.getAttribute("src")).toBe("/red-main.jpg");
        expect(main.getAttribute("alt")).toBe("A");
    });

    it("restores the base strip and main image on reset", () => {
        fireVariant({ large: "/red-main.jpg", label: "Red", tiles: variantTiles });
        fireVariant({ reset: true });

        const main = document.querySelector("[data-gallery-main]");
        expect(main.getAttribute("src")).toBe("/a.jpg");

        const thumbs = document.querySelectorAll("[data-gallery-thumb]");
        expect(thumbs).toHaveLength(2);
        expect([...thumbs].map((t) => t.dataset.large)).toEqual(["/a.jpg", "/b.jpg"]);
    });
});
