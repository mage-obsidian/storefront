// This file is part of the MageObsidian - Storefront project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect, beforeEach, vi } from "vitest";
import { nextTick } from "vue";

function greeting(): HTMLElement {
    document.body.innerHTML = `
        <div data-persistent-greeting data-welcome="Welcome, %1!" data-login-url="https://shop.test/customer/account/login/" hidden>
            <span data-persistent-name></span>
            <a data-persistent-not-you href="/persistent/index/unsetCookie/">Not you?</a>
        </div>`;
    return document.querySelector("[data-persistent-greeting]") as HTMLElement;
}

async function mount(): Promise<void> {
    await import("./persistent-greeting.ts");
    await nextTick();
}

beforeEach(() => {
    vi.resetModules();
    document.body.innerHTML = "";
});

describe("persistent-greeting", () => {
    it("names a recognised visitor and offers the way out", async () => {
        const element = greeting();
        const customerData = await import("MageObsidian_ModernFrontend::js/customer-data");
        customerData.__setSection("persistent", { fullname: "Ada Lovelace" });

        await mount();

        expect(element.hidden).toBe(false);
        expect(element.querySelector("[data-persistent-name]")!.textContent).toBe("Welcome, Ada Lovelace!");
    });

    it("stays out of the way of a signed-in customer", async () => {
        const element = greeting();
        const customerData = await import("MageObsidian_ModernFrontend::js/customer-data");
        customerData.__setSection("persistent", { fullname: "Ada Lovelace" });
        customerData.__setSection("customer", { firstname: "Ada" });

        await mount();

        expect(element.hidden).toBe(true);
    });

    it("stays hidden when nobody is recognised", async () => {
        const element = greeting();

        await mount();

        expect(element.hidden).toBe(true);
    });

    it("appears when the section arrives after the page", async () => {
        const element = greeting();
        await mount();
        const customerData = await import("MageObsidian_ModernFrontend::js/customer-data");

        customerData.__setSection("persistent", { fullname: "Ada Lovelace" });
        await nextTick();

        expect(element.hidden).toBe(false);
    });

    it("does not insert the name as markup", async () => {
        const element = greeting();
        const customerData = await import("MageObsidian_ModernFrontend::js/customer-data");
        customerData.__setSection("persistent", { fullname: "<img src=x onerror=alert(1)>" });

        await mount();

        expect(element.querySelector("img")).toBeNull();
    });

    describe("saying it is not them", () => {
        it("forgets the visitor, refreshes what the page remembers and sends them to sign in", async () => {
            const element = greeting();
            const customerData = await import("MageObsidian_ModernFrontend::js/customer-data");
            customerData.__setSection("persistent", { fullname: "Ada Lovelace" });
            const fetchMock = vi.fn().mockResolvedValue({ ok: true });
            vi.stubGlobal("fetch", fetchMock);
            const assign = vi.fn();
            Object.defineProperty(window, "location", { value: { assign, href: "https://shop.test/" }, writable: true, configurable: true });
            await mount();

            const click = new MouseEvent("click", { bubbles: true, cancelable: true });
            element.querySelector("[data-persistent-not-you]")!.dispatchEvent(click);
            await vi.waitFor(() => expect(assign).toHaveBeenCalled());

            expect(click.defaultPrevented).toBe(true);
            expect(String(fetchMock.mock.calls[0][0])).toContain("/persistent/index/unsetCookie/");
            expect(customerData.reload.calls).toEqual([[[]]]);
            expect(assign).toHaveBeenCalledWith("https://shop.test/customer/account/login/");
            vi.unstubAllGlobals();
        });
    });
});
