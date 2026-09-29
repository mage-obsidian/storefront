// This file is part of the MageObsidian - Storefront project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { afterEach, describe, expect, it } from "vitest";

const source = readFileSync(join(import.meta.dirname, "navigation-retention.head.js"), "utf8");

type Activation = { from: object | null; navigationType: string } | null;

function run(activation: Activation, marker: string | null = "obsidian-main-end", withApi = true): void {
    const script = document.createElement("script");
    if (marker !== null) {
        script.setAttribute("data-marker", marker);
    }
    Object.defineProperty(document, "currentScript", { value: script, configurable: true });
    const win = withApi ? { navigation: { activation } } : {};
    new Function("window", "document", source)(win, document);
}

const expectLinks = (): HTMLLinkElement[] => Array.from(document.head.querySelectorAll("link[rel=expect]"));

afterEach(() => {
    document.head.innerHTML = "";
});

describe("navigation retention", () => {
    it("holds rendering until the marker when arriving from a page of the same store", () => {
        run({ from: {}, navigationType: "push" });

        const [link] = expectLinks();
        expect(expectLinks()).toHaveLength(1);
        expect(link.getAttribute("href")).toBe("#obsidian-main-end");
        expect(link.getAttribute("blocking")).toBe("render");
    });

    it("holds on a history traversal that rebuilds the page", () => {
        run({ from: {}, navigationType: "traverse" });

        expect(expectLinks()).toHaveLength(1);
    });

    it("leaves a first visit or an arrival from another site to paint progressively", () => {
        run({ from: null, navigationType: "push" });

        expect(expectLinks()).toHaveLength(0);
    });

    it("leaves a reload to paint progressively", () => {
        run({ from: {}, navigationType: "reload" });

        expect(expectLinks()).toHaveLength(0);
    });

    it("does nothing while the document has not been activated", () => {
        run(null);

        expect(expectLinks()).toHaveLength(0);
    });

    it("does nothing in a browser without the Navigation API", () => {
        run({ from: {}, navigationType: "push" }, "obsidian-main-end", false);

        expect(expectLinks()).toHaveLength(0);
    });

    it("does nothing without a marker to wait for", () => {
        run({ from: {}, navigationType: "push" }, null);

        expect(expectLinks()).toHaveLength(0);
    });
});
