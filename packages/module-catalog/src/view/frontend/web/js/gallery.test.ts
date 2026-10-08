// This file is part of the MageObsidian - Catalog project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import { init } from "./gallery";

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

    it("clones the server's thumb, so the markup only lives in the template", () => {
        document.body.innerHTML = `
            <div data-pdp>
                <img data-gallery-main src="/a.jpg" alt="A">
                <ul data-gallery-thumbs data-thumb-label="Show image %1">
                    <li class="tile"><button class="pdp__thumb custom-chain" data-gallery-thumb data-large="/a.jpg" data-label="A" aria-pressed="true"><img loading="lazy" decoding="async"></button></li>
                </ul>
            </div>`;
        init();

        fireVariant({ large: "/red-main.jpg", label: "Red", tiles: variantTiles });

        const item = document.querySelector("[data-gallery-thumbs] li");
        const button = item.querySelector("[data-gallery-thumb]");
        expect(item.className).toBe("tile");
        expect(button.className).toBe("pdp__thumb custom-chain");
        expect(button.querySelector("img").getAttribute("decoding")).toBe("async");
        // Rebuilt thumbs are already in view, so they stop being lazy.
        expect(button.querySelector("img").getAttribute("loading")).toBe("eager");
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

describe("gallery enhancer under a view transition", () => {
    let decodes;
    let callbacks;

    beforeEach(() => {
        decodes = [];
        callbacks = [];
        class StubImage {
            set src(value) {
                this.pending = value;
            }
            decode() {
                decodes.push(this.pending);
                return Promise.resolve();
            }
        }
        vi.stubGlobal("Image", StubImage);
        document.startViewTransition = (callback) => {
            callbacks.push(callback);
            callback();
            return { finished: Promise.resolve() };
        };
        setup();
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        delete document.startViewTransition;
    });

    it("decodes the incoming image before opening the transition", async () => {
        document.querySelectorAll("[data-gallery-thumb]")[1].click();

        expect(decodes).toEqual(["/b.jpg"]);
        expect(callbacks).toHaveLength(0);

        await vi.waitFor(() => expect(callbacks).toHaveLength(1));
        expect(document.querySelector("[data-gallery-main]").getAttribute("src")).toBe("/b.jpg");
    });

    it("decodes the variant's thumbs too, and rebuilds the strip inside the transition", async () => {
        fireVariant({ large: "/red-main.jpg", label: "Red", tiles: variantTiles });

        expect([...new Set(decodes)]).toEqual(["/red-main.jpg", "/red-t1.jpg", "/red-t2.jpg", "/red-t3.jpg"]);
        expect(document.querySelectorAll("[data-gallery-thumb]")).toHaveLength(2);

        await vi.waitFor(() => expect(callbacks.length).toBeGreaterThan(0));
        expect(document.querySelectorAll("[data-gallery-thumb]")).toHaveLength(3);
        expect(document.querySelector("[data-gallery-main]").getAttribute("src")).toBe("/red-main.jpg");
    });

    it("swaps anyway when the image never decodes", async () => {
        vi.stubGlobal("Image", class {
            set src(value) {
                this.pending = value;
            }
            decode() {
                return new Promise(() => {});
            }
        });
        document.querySelectorAll("[data-gallery-thumb]")[1].click();

        await vi.waitFor(
            () => expect(document.querySelector("[data-gallery-main]").getAttribute("src")).toBe("/b.jpg"),
            { timeout: 2000 },
        );
    });
});

describe("gallery enhancer and the responsive srcset", () => {
    function setupWithSrcset() {
        document.body.innerHTML = `
        <div data-pdp>
            <img data-gallery-main src="/a.jpg" srcset="/a-400.jpg 400w, /a-800.jpg 800w" alt="A">
            <ul data-gallery-thumbs data-thumb-label="Show image %1">
                <li><button data-gallery-thumb data-large="/a.jpg" data-large-srcset="/a-400.jpg 400w, /a-800.jpg 800w" data-label="A" aria-pressed="true"><img></button></li>
                <li><button data-gallery-thumb data-large="/b.jpg" data-large-srcset="/b-400.jpg 400w, /b-800.jpg 800w" data-label="B" aria-pressed="false"><img></button></li>
                <li><button data-gallery-thumb data-large="/c.jpg" data-large-srcset="" data-label="C" aria-pressed="false"><img></button></li>
            </ul>
        </div>`;
        init();
    }

    beforeEach(setupWithSrcset);

    it("replaces the candidate list along with the src, so the old photo cannot win", () => {
        document.querySelectorAll("[data-gallery-thumb]")[1].click();

        const main = document.querySelector("[data-gallery-main]");
        expect(main.getAttribute("src")).toBe("/b.jpg");
        expect(main.getAttribute("srcset")).toBe("/b-400.jpg 400w, /b-800.jpg 800w");
    });

    it("drops the candidate list when the next image has none", () => {
        document.querySelectorAll("[data-gallery-thumb]")[2].click();

        const main = document.querySelector("[data-gallery-main]");
        expect(main.getAttribute("src")).toBe("/c.jpg");
        expect(main.hasAttribute("srcset")).toBe(false);
    });

    it("carries the candidate list through a variant swap and restores it on reset", () => {
        fireVariant({ large: "/red.jpg", largeSrcset: "/red-400.jpg 400w", label: "Red" });

        const main = document.querySelector("[data-gallery-main]");
        expect(main.getAttribute("srcset")).toBe("/red-400.jpg 400w");

        fireVariant({ reset: true });
        expect(main.getAttribute("src")).toBe("/a.jpg");
        expect(main.getAttribute("srcset")).toBe("/a-400.jpg 400w, /a-800.jpg 800w");
    });

    it("puts the candidate list on the thumbs it rebuilds for a variant", () => {
        fireVariant({
            tiles: [
                { large: "/red-main.jpg", largeSrcset: "/red-main-400.jpg 400w", thumb: "/red-t1.jpg", label: "Red" },
                { large: "/red-back.jpg", thumb: "/red-t2.jpg", label: "Red back" },
            ],
        });

        const thumbs = document.querySelectorAll("[data-gallery-thumb]");
        expect(thumbs[0].dataset.largeSrcset).toBe("/red-main-400.jpg 400w");

        thumbs[1].click();
        const main = document.querySelector("[data-gallery-main]");
        expect(main.getAttribute("src")).toBe("/red-back.jpg");
        expect(main.hasAttribute("srcset")).toBe(false);
    });
});

describe("gallery video facade", () => {
    const EMBED = "https://www.youtube-nocookie.com/embed/abc?autoplay=1&rel=0";

    function video(firstIsVideo = false) {
        const embed = `data-video-embed="${EMBED}" data-video-title="Unboxing"`;
        document.body.innerHTML = `
            <div data-pdp>
                <figure>
                    <img data-gallery-main src="/a.jpg" alt="A">
                    <div data-gallery-video hidden></div>
                    <button type="button" data-gallery-play aria-label="Play video" ${firstIsVideo ? embed : "hidden"}></button>
                </figure>
                <ul data-gallery-thumbs data-thumb-label="Show image %1">
                    <li><button data-gallery-thumb data-large="/a.jpg" data-label="A" aria-pressed="true" ${firstIsVideo ? embed : ""}><img></button></li>
                    <li><button data-gallery-thumb data-large="/v.jpg" data-label="Clip" aria-pressed="false" ${firstIsVideo ? "" : embed}>
                        <img><span data-gallery-video-badge></span></button></li>
                </ul>
            </div>`;
        init();
    }

    const play = () => document.querySelector<HTMLButtonElement>("[data-gallery-play]")!;
    const stage = () => document.querySelector<HTMLElement>("[data-gallery-video]")!;
    const thumbs = () => document.querySelectorAll<HTMLElement>("[data-gallery-thumb]");
    const main = () => document.querySelector<HTMLElement>("[data-gallery-main]")!;

    it("creates no frame while the page is only displayed", () => {
        video();

        expect(document.querySelectorAll("iframe")).toHaveLength(0);
        expect(play().hidden).toBe(true);
    });

    it("offers to play when the chosen tile is the video, and shows its preview", () => {
        video();

        thumbs()[1].click();

        expect(play().hidden).toBe(false);
        expect(main().getAttribute("src")).toBe("/v.jpg");
        expect(document.querySelectorAll("iframe")).toHaveLength(0);
    });

    it("offers to play from the start when the first tile is the video", () => {
        video(true);

        expect(play().hidden).toBe(false);
        expect(document.querySelectorAll("iframe")).toHaveLength(0);
    });

    it("loads the provider's player only when asked, in place of the preview", () => {
        video();
        thumbs()[1].click();

        play().click();

        const frames = stage().querySelectorAll("iframe");
        expect(frames).toHaveLength(1);
        expect(frames[0].getAttribute("src")).toBe(EMBED);
        expect(frames[0].getAttribute("title")).toBe("Unboxing");
        expect(frames[0].hasAttribute("allowfullscreen")).toBe(true);
        expect(frames[0].getAttribute("referrerpolicy")).toBe("strict-origin-when-cross-origin");
        expect(stage().hidden).toBe(false);
        expect(main().hidden).toBe(true);
        expect(play().hidden).toBe(true);
    });

    it("takes the player away when another tile is chosen", () => {
        video();
        thumbs()[1].click();
        play().click();

        thumbs()[0].click();

        expect(document.querySelectorAll("iframe")).toHaveLength(0);
        expect(stage().hidden).toBe(true);
        expect(main().hidden).toBe(false);
        expect(play().hidden).toBe(true);
    });

    it("takes the player away when a variant changes the gallery", () => {
        video();
        thumbs()[1].click();
        play().click();

        fireVariant({ large: "/red-main.jpg", label: "Red", tiles: variantTiles });

        expect(document.querySelectorAll("iframe")).toHaveLength(0);
        expect(main().hidden).toBe(false);
        expect(play().hidden).toBe(true);
    });

    it("does not carry the video over to the tiles a variant brings", () => {
        video(true);

        fireVariant({ large: "/red-main.jpg", label: "Red", tiles: variantTiles });

        const rebuilt = thumbs();
        expect(rebuilt).toHaveLength(3);
        expect([...rebuilt].some((thumb) => thumb.hasAttribute("data-video-embed"))).toBe(false);
        expect(document.querySelector("[data-gallery-video-badge]")).toBeNull();
        expect(play().hidden).toBe(true);
    });

    it("offers the first tile's video again after a reset", () => {
        video(true);
        fireVariant({ large: "/red-main.jpg", label: "Red", tiles: variantTiles });

        fireVariant({ reset: true });

        expect(play().hidden).toBe(false);
    });
});
