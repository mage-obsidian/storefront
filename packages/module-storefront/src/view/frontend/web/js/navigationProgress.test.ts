// This file is part of the MageObsidian - Storefront project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { afterEach, beforeEach, describe, expect, it } from "vitest";
import { bindNavigationProgress } from "./navigationProgress.ts";

type NavigateInit = {
    url?: string;
    sameDocument?: boolean;
    hashChange?: boolean;
    downloadRequest?: string | null;
};

class FakeNavigation extends EventTarget {}

let navigation: FakeNavigation;
let unbind: () => void;
let reducedMotion = false;

const navigate = (init: NavigateInit = {}): void => {
    const event = Object.assign(new Event("navigate"), {
        destination: { url: init.url ?? `${location.origin}/gear/bags.html`, sameDocument: init.sameDocument ?? false },
        hashChange: init.hashChange ?? false,
        downloadRequest: init.downloadRequest ?? null,
    });
    navigation.dispatchEvent(event);
};

const indicator = (): HTMLElement => document.querySelector("[data-navigation-progress]") as HTMLElement;
const active = (): boolean => indicator().hasAttribute("data-active");

beforeEach(() => {
    document.body.innerHTML = "<main><a href='/gear/bags.html'>Bags</a></main>";
    navigation = new FakeNavigation();
    reducedMotion = false;
    const win = Object.assign(window, {
        navigation,
        matchMedia: (query: string) => ({ matches: reducedMotion && query.includes("reduce") }),
    });
    unbind = bindNavigationProgress(win as unknown as Window & typeof globalThis);
});

afterEach(() => {
    unbind();
});

describe("navigation progress", () => {
    it("stays idle and silent until a navigation starts", () => {
        expect(active()).toBe(false);
        expect(indicator().textContent).toBe("");
    });

    it("shows at once when the shopper leaves for another page of the store", () => {
        navigate();

        expect(active()).toBe(true);
        expect(indicator().textContent).not.toBe("");
    });

    it("announces politely without taking focus", () => {
        const focused = document.activeElement;

        navigate();

        expect(indicator().getAttribute("role")).toBe("status");
        expect(indicator().getAttribute("aria-live")).toBe("polite");
        expect(document.activeElement).toBe(focused);
    });

    it("never covers what the shopper might click", () => {
        expect(indicator().getAttribute("aria-hidden")).toBeNull();
        expect(indicator().classList.contains("navigation-progress")).toBe(true);
    });

    it.each([
        ["a navigation the page handles itself", { sameDocument: true }],
        ["a jump to an anchor of the same page", { hashChange: true, sameDocument: true }],
        ["a download", { downloadRequest: "invoice.pdf" }],
        ["a page of another site", { url: "https://elsewhere.example/page" }],
    ])("stays idle for %s", (_label, init) => {
        navigate(init);

        expect(active()).toBe(false);
    });

    it("hides when the navigation fails or is stopped", () => {
        navigate();
        navigation.dispatchEvent(new Event("navigateerror"));

        expect(active()).toBe(false);
        expect(indicator().textContent).toBe("");
    });

    it("hides when the page comes back from the back/forward cache", () => {
        navigate();
        window.dispatchEvent(Object.assign(new Event("pageshow"), { persisted: true }));

        expect(active()).toBe(false);
    });

    it("keeps showing on the first load of a page", () => {
        navigate();
        window.dispatchEvent(Object.assign(new Event("pageshow"), { persisted: false }));

        expect(active()).toBe(true);
    });

    it("hides when a same-page navigation replaces the one in progress", () => {
        navigate();
        navigate({ sameDocument: true });

        expect(active()).toBe(false);
    });

    it("holds still when the shopper prefers reduced motion", () => {
        reducedMotion = true;

        navigate();

        expect(indicator().hasAttribute("data-still")).toBe(true);
    });

    it("animates otherwise", () => {
        navigate();

        expect(indicator().hasAttribute("data-still")).toBe(false);
    });

    it("stops listening once unbound", () => {
        unbind();
        navigate();

        expect(document.querySelector("[data-navigation-progress]")).toBeNull();
        unbind = () => {};
    });
});
