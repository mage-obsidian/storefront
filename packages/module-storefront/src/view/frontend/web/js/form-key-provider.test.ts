import { describe, it, expect, beforeEach } from "vitest";
import { ensureFormKey } from "./form-key-provider.ts";

beforeEach(() => {
    document.cookie = "form_key=; max-age=0; path=/";
    document.body.innerHTML = "";
});

describe("form-key provider", () => {
    it("reuses an existing cookie and aligns rendered inputs", () => {
        document.cookie = "form_key=existing123; path=/";
        document.body.innerHTML = '<input name="form_key" value="stale">';

        const key = ensureFormKey();

        expect(key).toBe("existing123");
        expect(document.querySelector('input[name="form_key"]').value).toBe("existing123");
    });

    it("generates and persists a cookie when none exists", () => {
        const key = ensureFormKey();

        expect(key).toHaveLength(16);
        expect(document.cookie).toContain(`form_key=${key}`);
    });
});
