import { describe, it, expect, beforeEach } from "vitest";
import { ensureFormKey, getFormKey, stampFormKey } from "./form-key-provider.ts";

function readCookie(): string {
    return document.cookie.match(/(?:^|;\s*)form_key=([^;]+)/)?.[1] ?? "";
}

beforeEach(() => {
    document.cookie = "form_key=; max-age=0; path=/";
    document.body.innerHTML = "";
});

describe("getFormKey", () => {
    it("returns the existing cookie untouched", () => {
        document.cookie = "form_key=existing123; path=/";

        expect(getFormKey()).toBe("existing123");
    });

    it("creates and persists a cookie when none exists", () => {
        const key = getFormKey();

        expect(key).toHaveLength(16);
        expect(readCookie()).toBe(key);
    });

    it("leaves the DOM alone", () => {
        document.body.innerHTML = '<input name="form_key" value="stale">';

        getFormKey();

        expect(document.querySelector("input")!.value).toBe("stale");
    });
});

describe("ensureFormKey", () => {
    it("reuses an existing cookie and aligns rendered inputs", () => {
        document.cookie = "form_key=existing123; path=/";
        document.body.innerHTML = '<input name="form_key" value="stale">';

        const key = ensureFormKey();

        expect(key).toBe("existing123");
        expect(document.querySelector("input")!.value).toBe("existing123");
    });

    it("generates and persists a cookie when none exists", () => {
        const key = ensureFormKey();

        expect(key).toHaveLength(16);
        expect(readCookie()).toBe(key);
    });
});

describe("stampFormKey", () => {
    it("only touches inputs under the given root", () => {
        document.cookie = "form_key=live999; path=/";
        document.body.innerHTML = `
            <form id="a"><input name="form_key" value="stale-a"></form>
            <form id="b"><input name="form_key" value="stale-b"></form>`;

        stampFormKey(document.getElementById("a")!);

        expect(document.querySelector("#a input")!.value).toBe("live999");
        expect(document.querySelector("#b input")!.value).toBe("stale-b");
    });
});

describe("submit stamping", () => {
    it("corrects a form that was rendered after the module ran", () => {
        document.cookie = "form_key=live999; path=/";
        document.body.innerHTML = '<form><input name="form_key" value="baked"></form>';

        document.querySelector("form")!.dispatchEvent(new Event("submit", { cancelable: true }));

        expect(document.querySelector("input")!.value).toBe("live999");
    });

    it("recreates the key when the cookie expired while the tab sat open", () => {
        document.body.innerHTML = '<form><input name="form_key" value="orphan"></form>';

        document.querySelector("form")!.dispatchEvent(new Event("submit", { cancelable: true }));

        const stamped = document.querySelector("input")!.value;
        expect(stamped).toHaveLength(16);
        expect(readCookie()).toBe(stamped);
    });
});
