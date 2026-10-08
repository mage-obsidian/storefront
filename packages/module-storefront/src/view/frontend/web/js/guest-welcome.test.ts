// This file is part of the MageObsidian - Storefront project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { beforeEach, describe, expect, it, vi } from "vitest";
import { nextTick } from "vue";

const welcome = (): HTMLElement => {
    document.body.innerHTML = `<span data-guest-welcome>Free shipping over 50</span>`;
    return document.querySelector("[data-guest-welcome]") as HTMLElement;
};

const mount = async (): Promise<void> => {
    await import("./guest-welcome.ts");
    await nextTick();
};

beforeEach(() => {
    vi.resetModules();
    document.body.innerHTML = "";
});

describe("guest-welcome", () => {
    it("is shown to a visitor nobody has signed in", async () => {
        const element = welcome();

        await mount();

        expect(element.hidden).toBe(false);
    });

    it("steps aside once the visitor is a signed-in customer", async () => {
        const element = welcome();
        const customerData = await import("MageObsidian_ModernFrontend::js/customer-data");
        customerData.__setSection("customer", { firstname: "Ada" });

        await mount();

        expect(element.hidden).toBe(true);
    });

    it("steps aside when the customer section arrives after the page", async () => {
        const element = welcome();
        await mount();
        const customerData = await import("MageObsidian_ModernFrontend::js/customer-data");

        customerData.__setSection("customer", { firstname: "Ada" });
        await nextTick();

        expect(element.hidden).toBe(true);
    });

    it("does nothing on a page without the message", async () => {
        document.body.innerHTML = "<main></main>";

        await expect(mount()).resolves.toBeUndefined();
    });
});
