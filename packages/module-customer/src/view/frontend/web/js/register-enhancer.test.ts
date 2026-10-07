// This file is part of the MageObsidian - Customer project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { beforeEach, describe, expect, it, vi } from "vitest";
import { withCookiesOff, NO_COOKIES_URL } from "../../../../Test/Js/cookiesOff.ts";

const render = (): HTMLFormElement => {
    document.body.innerHTML = `
        <form data-register-form action="/customer/account/createPost" method="post">
            <input name="firstname" value="Ada">
            <input name="lastname" value="Lovelace">
            <input name="email" value="ada@obsidian.test">
            <input name="password" type="password" value="Str0ng-passw0rd!">
            <input name="password_confirmation" type="password" value="Str0ng-passw0rd!">
            <button type="submit">Create</button>
        </form>`;
    return document.querySelector("form") as HTMLFormElement;
};

beforeEach(() => {
    vi.resetModules();
    document.body.innerHTML = "";
});

describe("register-enhancer", () => {
    it("holds the form and sends the shopper to the no-cookies page when cookies are off", async () => {
        const form = render();
        const off = withCookiesOff();
        await import("./register-enhancer.ts");

        const submit = new Event("submit", { bubbles: true, cancelable: true });
        form.dispatchEvent(submit);

        expect(submit.defaultPrevented).toBe(true);
        expect(off.visited).toEqual([NO_COOKIES_URL]);
        off.restore();
    });

    it("leaves the form alone when cookies work", async () => {
        const form = render();
        document.head.innerHTML = "";
        await import("./register-enhancer.ts");
        const outcomes: boolean[] = [];
        form.addEventListener("submit", (event) => outcomes.push(event.defaultPrevented));

        form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));

        expect(outcomes).toEqual([false]);
    });
});
