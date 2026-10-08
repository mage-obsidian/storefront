// This file is part of the MageObsidian - Storefront project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { beforeEach, describe, expect, it } from "vitest";
import { startConversion, type ConversionConfig } from "./google-conversion.ts";

const CONFIG: ConversionConfig = {
    isCookieRestrictionModeEnabled: false,
    currentWebsite: 1,
    cookieName: "user_allowed_save_cookie",
    src: "https://www.googleadservices.com/pagead/conversion.js",
    variables: {
        google_conversion_id: 123456,
        google_conversion_language: "en",
        google_conversion_format: "2",
        google_conversion_color: "FFFFFF",
        google_conversion_label: "thanks",
        google_conversion_value: 99.5,
        google_conversion_currency: "USD",
    },
};

const declare = (config: Partial<ConversionConfig>): void => {
    const payload = JSON.stringify({ ...CONFIG, ...config }).replace(/"/g, "&quot;");
    document.body.innerHTML = `<div data-google-conversion="${payload}" hidden></div>`;
};

const globals = (): Record<string, unknown> => window as unknown as Record<string, unknown>;

beforeEach(() => {
    document.body.innerHTML = "";
    document.head.querySelectorAll("script").forEach((script) => script.remove());
    Object.keys(CONFIG.variables).forEach((name) => delete globals()[name]);
    document.cookie = "user_allowed_save_cookie=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
});

describe("startConversion", () => {
    it("hands the configured values to the conversion script and loads it", () => {
        declare({});

        startConversion(document, window);

        expect(globals().google_conversion_id).toBe(123456);
        expect(globals().google_conversion_label).toBe("thanks");
        expect(globals().google_conversion_value).toBe(99.5);
        expect(globals().google_conversion_currency).toBe("USD");
        expect(document.head.querySelector(`script[src="${CONFIG.src}"]`)).not.toBeNull();
    });

    it("does nothing when the store restricts cookies and none were accepted", () => {
        declare({ isCookieRestrictionModeEnabled: true });

        startConversion(document, window);

        expect(globals().google_conversion_id).toBeUndefined();
        expect(document.head.querySelectorAll("script")).toHaveLength(0);
    });

    it("converts once this website has accepted cookies", () => {
        document.cookie = `user_allowed_save_cookie=${encodeURIComponent('{"1":1}')}; path=/`;
        declare({ isCookieRestrictionModeEnabled: true });

        startConversion(document, window);

        expect(document.head.querySelectorAll("script")).toHaveLength(1);
    });

    it("loads the conversion script once however often it runs", () => {
        declare({});

        startConversion(document, window);
        startConversion(document, window);

        expect(document.head.querySelectorAll("script")).toHaveLength(1);
    });

    it("survives a payload it cannot read", () => {
        document.body.innerHTML = '<div data-google-conversion="{oops"></div>';

        expect(() => startConversion(document, window)).not.toThrow();
    });
});
