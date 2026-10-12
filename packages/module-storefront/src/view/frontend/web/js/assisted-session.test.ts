// This file is part of the MageObsidian - Storefront project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { beforeEach, describe, expect, it } from "vitest";
import { PINNED_OFFSET, noticeFor, renderNotice, syncPinnedOffset, trackPinnedOffset } from "./assisted-session.ts";

const banner = (): HTMLElement => {
    document.body.innerHTML = `
        <div data-assisted-session="You are connected as %1 on %2" hidden>
            <p data-assisted-session-text></p>
            <a href="/customer/account/logout/">Close session</a>
        </div>`;
    return document.querySelector("[data-assisted-session]") as HTMLElement;
};

const text = (): string => document.querySelector("[data-assisted-session-text]")?.textContent ?? "";

beforeEach(() => {
    document.body.innerHTML = "";
    document.documentElement.style.removeProperty(PINNED_OFFSET);
});

const withHeight = (element: HTMLElement, height: number): HTMLElement => {
    element.getBoundingClientRect = () => ({ height }) as DOMRect;
    return element;
};

const offset = (): string => document.documentElement.style.getPropertyValue(PINNED_OFFSET);

describe("noticeFor", () => {
    it("names the customer and the store the session belongs to", () => {
        expect(
            noticeFor({ adminUserId: 3, websiteName: "Main Website" }, "Ada Obsidian", "You are connected as %1 on %2"),
        ).toBe("You are connected as Ada Obsidian on Main Website");
    });
});

describe("renderNotice", () => {
    /**
     * The point of the banner: a customer whose session an administrator is
     * inside has a right to know, and this is the only thing on the page that
     * says so.
     */
    it("shows who is being assisted once the sections arrive", () => {
        const element = banner();

        expect(renderNotice(element, { adminUserId: 3, websiteName: "Main Website" }, { fullname: "Ada" })).toBe(true);
        expect(element.hidden).toBe(false);
        expect(text()).toBe("You are connected as Ada on Main Website");
    });

    // The markup ships on every page, cacheable and empty; an ordinary shopper
    // must never see it.
    it("stays hidden for an ordinary shopper", () => {
        const element = banner();

        expect(renderNotice(element, {}, { fullname: "Ada" })).toBe(false);
        expect(element.hidden).toBe(true);
    });

    it("stays hidden before the sections have arrived", () => {
        const element = banner();

        expect(renderNotice(element, undefined, undefined)).toBe(false);
        expect(element.hidden).toBe(true);
    });

    // An id of zero is not an administrator, and neither is an empty string.
    it("does not take an empty administrator for a real one", () => {
        expect(renderNotice(banner(), { adminUserId: "", websiteName: "Main" }, { fullname: "Ada" })).toBe(false);
    });
});

describe("syncPinnedOffset", () => {
    it("publishes the height of a shown banner so the pinned header sits below it", () => {
        const element = withHeight(banner(), 44.4);
        element.hidden = false;

        syncPinnedOffset(element);

        expect(offset()).toBe("44px");
    });

    it("clears the offset once the banner is hidden again", () => {
        const element = withHeight(banner(), 44);
        element.hidden = false;
        syncPinnedOffset(element);

        element.hidden = true;
        syncPinnedOffset(element);

        expect(offset()).toBe("");
    });
});

describe("trackPinnedOffset", () => {
    it("follows the banner when it wraps onto more lines", () => {
        const element = withHeight(banner(), 44);
        element.hidden = false;
        let resized: () => void = () => undefined;
        class FakeObserver {
            constructor(callback: () => void) {
                resized = callback;
            }
            observe(): void {}
            unobserve(): void {}
            disconnect(): void {}
        }

        trackPinnedOffset(element, document.documentElement, FakeObserver as unknown as typeof ResizeObserver);
        expect(offset()).toBe("44px");

        withHeight(element, 88);
        resized();

        expect(offset()).toBe("88px");
    });

    it("publishes nothing while the banner stays hidden", () => {
        trackPinnedOffset(withHeight(banner(), 44), document.documentElement, undefined);

        expect(offset()).toBe("");
    });
});
