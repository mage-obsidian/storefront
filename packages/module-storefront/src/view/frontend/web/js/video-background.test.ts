import { beforeEach, describe, expect, it, vi } from "vitest";
import {
    enhanceVideoBackgrounds,
    isSelfHosted,
    playsOnlyWhenVisible,
    posterOf,
    sourceOf,
} from "./video-background.ts";

const build = (attributes: string): HTMLElement => {
    document.body.innerHTML = `<div data-background-type="video" ${attributes}><p>Row</p></div>`;
    return document.querySelector("[data-background-type]") as HTMLElement;
};

const view = (): Window =>
    ({
        document,
        getComputedStyle: (element: Element) => window.getComputedStyle(element),
        IntersectionObserver: undefined,
    }) as unknown as Window;

beforeEach(() => {
    document.body.innerHTML = "";
});

describe("isSelfHosted", () => {
    it("accepts a video served by the store", () => {
        expect(isSelfHosted("https://shop.test/media/hero.mp4")).toBe(true);
    });

    it("refuses a provider that would load a third party", () => {
        expect(isSelfHosted("https://www.youtube.com/watch?v=abc")).toBe(false);
        expect(isSelfHosted("https://player.vimeo.com/video/123")).toBe(false);
        expect(isSelfHosted("https://youtu.be/abc")).toBe(false);
    });

    it("refuses an empty source", () => {
        expect(isSelfHosted("")).toBe(false);
    });
});

describe("reading what the author chose", () => {
    it("reads the source and the fallback image", () => {
        const row = build('data-video-src=" /media/hero.mp4 " data-video-fallback-src="/media/poster.jpg"');

        expect(sourceOf(row)).toBe("/media/hero.mp4");
        expect(posterOf(row)).toBe("/media/poster.jpg");
    });

    it("plays only while visible unless the author turned that off", () => {
        expect(playsOnlyWhenVisible(build('data-video-play-only-visible="true"'))).toBe(true);
        expect(playsOnlyWhenVisible(build('data-video-play-only-visible="false"'))).toBe(false);
    });
});

describe("enhanceVideoBackgrounds", () => {
    it("adds a muted looping video that does not take the focus", () => {
        const row = build('data-video-src="/media/hero.mp4" data-video-fallback-src="/media/poster.jpg"');

        enhanceVideoBackgrounds(document, view());

        const video = row.querySelector("video") as HTMLVideoElement;
        expect(video).not.toBeNull();
        expect(video.muted).toBe(true);
        expect(video.loop).toBe(true);
        expect(video.getAttribute("playsinline")).toBe("");
        expect(video.getAttribute("aria-hidden")).toBe("true");
        expect(video.getAttribute("poster")).toBe("/media/poster.jpg");
    });

    it("shows the fallback image while the video is not there", () => {
        const row = build('data-video-src="/media/hero.mp4" data-video-fallback-src="/media/poster.jpg"');

        enhanceVideoBackgrounds(document, view());

        expect((row.querySelector("video") as HTMLVideoElement).getAttribute("poster")).toBe("/media/poster.jpg");
    });

    it("leaves a provider video to the gap register instead of loading a third party", () => {
        const row = build('data-video-src="https://www.youtube.com/watch?v=abc"');

        enhanceVideoBackgrounds(document, view());

        expect(row.querySelector("video")).toBeNull();
    });

    it("leaves a row with no source alone", () => {
        const row = build("");

        enhanceVideoBackgrounds(document, view());

        expect(row.querySelector("video")).toBeNull();
    });

    it("does not add a second video to the same row", () => {
        const row = build('data-video-src="/media/hero.mp4"');
        const current = view();

        enhanceVideoBackgrounds(document, current);
        enhanceVideoBackgrounds(document, current);

        expect(row.querySelectorAll("video")).toHaveLength(1);
    });

    it("pauses the video once the row leaves the screen", () => {
        const row = build('data-video-src="/media/hero.mp4"');
        let announce: ((entries: { isIntersecting: boolean }[]) => void) | undefined;
        const observing = {
            document,
            getComputedStyle: (element: Element) => window.getComputedStyle(element),
            IntersectionObserver: class {
                constructor(callback: (entries: { isIntersecting: boolean }[]) => void) {
                    announce = callback;
                }
                observe(): void {}
            },
        } as unknown as Window;

        enhanceVideoBackgrounds(document, observing);

        const video = row.querySelector("video") as HTMLVideoElement;
        video.play = vi.fn(() => Promise.resolve());
        video.pause = vi.fn();

        announce?.([{ isIntersecting: true }]);
        expect(video.play).toHaveBeenCalled();

        announce?.([{ isIntersecting: false }]);
        expect(video.pause).toHaveBeenCalled();
    });
});
