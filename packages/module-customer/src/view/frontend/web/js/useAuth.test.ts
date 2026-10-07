// This file is part of the MageObsidian - Customer project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { withCookiesOff, NO_COOKIES_URL } from "../../../../Test/Js/cookiesOff.ts";
import { useAuth, CAPTCHA_FORM_ID } from "./useAuth.ts";
import { reload, __reset } from "../../../../Test/Js/stubs/customerData.ts";

function mockFetch(response: { ok: boolean; body: unknown }): void {
    vi.stubGlobal(
        "fetch",
        vi.fn(() =>
            Promise.resolve({
                ok: response.ok,
                json: () => Promise.resolve(response.body),
            } as Response),
        ),
    );
}

describe("useAuth.login", () => {
    beforeEach(() => __reset());
    afterEach(() => vi.unstubAllGlobals());

    it("signs in, refreshes customer-data and surfaces the redirect", async () => {
        mockFetch({ ok: true, body: { errors: false, redirectUrl: "/customer/account" } });
        const { login } = useAuth();

        const result = await login({ url: "/customer/ajax/login", username: "ada@shop.test", password: "secret" });

        expect(result.ok).toBe(true);
        expect(result.redirectUrl).toBe("/customer/account");
        expect(reload.calls).toHaveLength(1); // sections reloaded once on success
    });

    it("reports the server error and leaves sections untouched", async () => {
        mockFetch({ ok: true, body: { errors: true, message: "Invalid login or password." } });
        const { login } = useAuth();

        const result = await login({ url: "/customer/ajax/login", username: "ada@shop.test", password: "wrong" });

        expect(result.ok).toBe(false);
        expect(result.message).toBe("Invalid login or password.");
        expect(reload.calls).toHaveLength(0);
    });

    it("fails closed on a network error", async () => {
        vi.stubGlobal("fetch", vi.fn(() => Promise.reject(new Error("offline"))));
        const { login } = useAuth();

        const result = await login({ url: "/customer/ajax/login", username: "ada@shop.test", password: "secret" });

        expect(result.ok).toBe(false);
        expect(reload.calls).toHaveLength(0);
    });

    it("posts JSON credentials with the default context and captcha form", async () => {
        const fetchSpy = vi.fn(() =>
            Promise.resolve({ ok: true, json: () => Promise.resolve({ errors: false }) } as Response),
        );
        vi.stubGlobal("fetch", fetchSpy);
        const { login } = useAuth();

        await login({ url: "/customer/ajax/login", username: "ada@shop.test", password: "secret" });

        const [, init] = fetchSpy.mock.calls[0];
        expect(init?.method).toBe("POST");
        expect(JSON.parse(init?.body as string)).toEqual({
            username: "ada@shop.test",
            password: "secret",
            context: "default",
            captcha_form_id: CAPTCHA_FORM_ID,
            captcha_string: "",
        });
    });

    it("carries a captcha solution when the caller has one", async () => {
        const fetchSpy = vi.fn(() =>
            Promise.resolve({ ok: true, json: () => Promise.resolve({ errors: false }) } as Response),
        );
        vi.stubGlobal("fetch", fetchSpy);
        const { login } = useAuth();

        await login({
            url: "/customer/ajax/login",
            username: "ada@shop.test",
            password: "secret",
            captchaFormId: "guest_checkout",
            captchaString: "AB12CD",
        });

        const [, init] = fetchSpy.mock.calls[0];
        expect(JSON.parse(init?.body as string)).toMatchObject({
            captcha_form_id: "guest_checkout",
            captcha_string: "AB12CD",
        });
    });

    it("carries the reCAPTCHA token under the name the core reads it from", async () => {
        const fetchSpy = vi.fn(() =>
            Promise.resolve({ ok: true, json: () => Promise.resolve({ errors: false }) } as Response),
        );
        vi.stubGlobal("fetch", fetchSpy);
        const { login } = useAuth();

        await login({ url: "/customer/ajax/login", username: "a@b.test", password: "pw", recaptchaToken: "tok-1" });

        const sent = JSON.parse((fetchSpy.mock.calls[0] as unknown as [string, RequestInit])[1].body as string);
        expect(sent["g-recaptcha-response"]).toBe("tok-1");
    });

    it("tells the platform the shopper asked to be remembered", async () => {
        const fetchSpy = vi.fn(() =>
            Promise.resolve({ ok: true, json: () => Promise.resolve({ errors: false }) } as Response),
        );
        vi.stubGlobal("fetch", fetchSpy);

        await useAuth().login({ url: "/customer/ajax/login", username: "a@b.test", password: "pw", rememberMe: true });

        const sent = JSON.parse((fetchSpy.mock.calls[0] as unknown as [string, RequestInit])[1].body as string);
        expect(sent.persistent_remember_me).toBe(true);
    });

    it("says nothing about remembering when the shopper did not ask", async () => {
        const fetchSpy = vi.fn(() =>
            Promise.resolve({ ok: true, json: () => Promise.resolve({ errors: false }) } as Response),
        );
        vi.stubGlobal("fetch", fetchSpy);

        await useAuth().login({ url: "/customer/ajax/login", username: "a@b.test", password: "pw" });

        const sent = JSON.parse((fetchSpy.mock.calls[0] as unknown as [string, RequestInit])[1].body as string);
        expect(sent).not.toHaveProperty("persistent_remember_me");
    });

    it("names the checkout in the query string, which is where the platform reads the context from", async () => {
        const fetchSpy = vi.fn(() =>
            Promise.resolve({ ok: true, json: () => Promise.resolve({ errors: false }) } as Response),
        );
        vi.stubGlobal("fetch", fetchSpy);

        await useAuth().login({ url: "/customer/ajax/login", username: "a@b.test", password: "pw", context: "checkout" });

        expect((fetchSpy.mock.calls[0] as unknown as [string])[0]).toBe("/customer/ajax/login?context=checkout");
    });

    it("leaves the url alone for the default context", async () => {
        const fetchSpy = vi.fn(() =>
            Promise.resolve({ ok: true, json: () => Promise.resolve({ errors: false }) } as Response),
        );
        vi.stubGlobal("fetch", fetchSpy);

        await useAuth().login({ url: "/customer/ajax/login", username: "a@b.test", password: "pw" });

        expect((fetchSpy.mock.calls[0] as unknown as [string])[0]).toBe("/customer/ajax/login");
    });

    it("sends no reCAPTCHA field when there is no token", async () => {
        const fetchSpy = vi.fn(() =>
            Promise.resolve({ ok: true, json: () => Promise.resolve({ errors: false }) } as Response),
        );
        vi.stubGlobal("fetch", fetchSpy);
        const { login } = useAuth();

        await login({ url: "/customer/ajax/login", username: "a@b.test", password: "pw" });

        const sent = JSON.parse((fetchSpy.mock.calls[0] as unknown as [string, RequestInit])[1].body as string);
        expect(sent).not.toHaveProperty("g-recaptcha-response");
    });
});

describe("useAuth.login with cookies off", () => {
    it("sends the shopper to the no-cookies page and posts nothing", async () => {
        const off = withCookiesOff();
        const fetchSpy = vi.fn();
        vi.stubGlobal("fetch", fetchSpy);

        const result = await useAuth().login({ url: "/customer/ajax/login", username: "a@b.test", password: "pw" });

        expect(result.ok).toBe(false);
        expect(fetchSpy).not.toHaveBeenCalled();
        expect(off.visited).toEqual([NO_COOKIES_URL]);
        off.restore();
        vi.unstubAllGlobals();
    });
});
