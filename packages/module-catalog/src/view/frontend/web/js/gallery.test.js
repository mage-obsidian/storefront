import { describe, it, expect, beforeEach } from "vitest";
import { init } from "./gallery.js";

// Gallery enhancer: thumbs swap the main image and the slot reacts to the
// configurable island's `obsidian:variant-image` event. happy-dom has no
// startViewTransition, so swaps apply synchronously.

function setup() {
    document.body.innerHTML = `
        <div data-pdp>
            <img data-gallery-main src="/a.jpg" alt="A">
            <button data-gallery-thumb data-large="/a.jpg" data-label="A" aria-pressed="true"><img></button>
            <button data-gallery-thumb data-large="/b.jpg" data-label="B" aria-pressed="false"><img></button>
        </div>`;
    init();
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

    it("swaps the main image when a variant image event fires", () => {
        window.dispatchEvent(
            new CustomEvent("obsidian:variant-image", { detail: { large: "/c.jpg", label: "C" } }),
        );

        const main = document.querySelector("[data-gallery-main]");
        expect(main.getAttribute("src")).toBe("/c.jpg");
        expect(main.getAttribute("alt")).toBe("C");
    });
});
