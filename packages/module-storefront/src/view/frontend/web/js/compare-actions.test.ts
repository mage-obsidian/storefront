// This file is part of the MageObsidian - Storefront project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect, afterEach, beforeEach, vi } from "vitest";
import events from "MageObsidian_ModernFrontend::js/events";
import { __setSection, __reset } from "MageObsidian_ModernFrontend::js/customer-data";
import { MutationPhase } from "mage-obsidian/runtime/mutationEvent.ts";
import { listingEvent } from "./listing-events.ts";

function cards(...ids: number[]): void {
    document.body.innerHTML = ids
        .map(
            (id) => `
        <form data-add-to-compare data-product-id="${id}" action="/catalog/product_compare/add" method="post">
            <button type="submit" aria-pressed="false">Compare</button>
        </form>`,
        )
        .join("");
}

function form(id: number): HTMLElement {
    return document.querySelector(`[data-product-id="${id}"]`) as HTMLElement;
}

beforeEach(() => {
    __reset();
});

afterEach(() => {
    document.body.innerHTML = "";
    vi.resetModules();
});

describe("compare-actions", () => {
    it("marks the products already in the compare list", async () => {
        __setSection("compare-products", { items: [{ id: 12 }] });
        cards(12, 34);
        await import("./compare-actions.ts");

        expect(form(12).classList.contains("is-compared")).toBe(true);
        expect(form(12).querySelector("button")?.getAttribute("aria-pressed")).toBe("true");
        expect(form(34).classList.contains("is-compared")).toBe(false);
    });

    it("marks the cards a filtered listing swaps in", async () => {
        __setSection("compare-products", { items: [{ id: 12 }] });
        cards(34);
        await import("./compare-actions.ts");

        cards(12, 34);
        await events.dispatch(listingEvent(MutationPhase.After), { url: "/women.html", result: ["products"] });

        expect(form(12).classList.contains("is-compared")).toBe(true);
        expect(form(12).querySelector("button")?.getAttribute("aria-pressed")).toBe("true");
        expect(form(34).classList.contains("is-compared")).toBe(false);
    });
});
