// This file is part of the MageObsidian - Storefront project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect, afterEach, vi } from "vitest";
import { withCookiesOff, NO_COOKIES_URL } from "../../../../Test/Js/cookiesOff.ts";

function heart(): HTMLFormElement {
    document.body.innerHTML = `
        <form data-add-to-wishlist data-product-id="7" action="/wishlist/index/add" method="post">
            <input type="hidden" name="product" value="7">
            <button type="submit" aria-pressed="false">Save</button>
        </form>`;
    return document.querySelector("form") as HTMLFormElement;
}

afterEach(() => {
    document.body.innerHTML = "";
    vi.resetModules();
});

describe("wishlist-actions", () => {
    it("holds a guest's native post and explains cookies when the browser refuses them", async () => {
        const form = heart();
        const off = withCookiesOff();
        await import("./wishlist-actions.ts");

        const submit = new Event("submit", { bubbles: true, cancelable: true });
        form.dispatchEvent(submit);

        expect(submit.defaultPrevented).toBe(true);
        expect(off.visited).toEqual([NO_COOKIES_URL]);
        off.restore();
    });

    it("lets a guest's native post through when cookies work", async () => {
        const form = heart();
        await import("./wishlist-actions.ts");

        const submit = new Event("submit", { bubbles: true, cancelable: true });
        form.dispatchEvent(submit);

        expect(submit.defaultPrevented).toBe(false);
    });
});
