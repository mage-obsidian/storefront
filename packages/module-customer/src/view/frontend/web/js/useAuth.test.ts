import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { useAuth } from "./useAuth.ts";
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

    it("posts JSON credentials with the default context", async () => {
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
        });
    });
});
