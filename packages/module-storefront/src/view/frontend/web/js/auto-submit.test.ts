// This file is part of the MageObsidian - Storefront project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { beforeEach, describe, expect, it, vi } from "vitest";

const render = (markup: string): void => {
    document.body.innerHTML = markup;
    for (const form of document.querySelectorAll("form")) {
        form.requestSubmit = vi.fn();
    }
};

const mount = async (): Promise<void> => {
    vi.resetModules();
    await import("./auto-submit.ts");
};

beforeEach(() => {
    document.body.innerHTML = "";
});

describe("auto-submit", () => {
    it("submits a form that asked for it as soon as the page is ready", async () => {
        render(`<form action="/go" method="post" data-auto-submit><button type="submit">Go</button></form>`);

        await mount();

        expect((document.querySelector("form") as HTMLFormElement).requestSubmit).toHaveBeenCalledTimes(1);
    });

    it("leaves a form that did not ask for it alone", async () => {
        render(`<form action="/go" method="post"><button type="submit">Go</button></form>`);

        await mount();

        expect((document.querySelector("form") as HTMLFormElement).requestSubmit).not.toHaveBeenCalled();
    });

    it("submits each requesting form once", async () => {
        render(`
            <form action="/one" method="post" data-auto-submit></form>
            <form action="/two" method="post" data-auto-submit></form>`);

        await mount();

        for (const form of document.querySelectorAll("form")) {
            expect(form.requestSubmit).toHaveBeenCalledTimes(1);
        }
    });

    it("does nothing on a page without forms", async () => {
        document.body.innerHTML = "<main></main>";

        await expect(mount()).resolves.toBeUndefined();
    });
});
