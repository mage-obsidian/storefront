import { describe, expect, it } from "vitest";
import { DEFAULT_CURRENCY_FORMAT, formatCurrency } from "./currency";

describe("formatCurrency", () => {
    it("puts the amount where the placeholder is", () => {
        expect(formatCurrency("$%s", 12.5)).toBe("$12.50");
        expect(formatCurrency("%s €", 12.5)).toBe("12.50 €");
    });

    it("always renders two decimals", () => {
        expect(formatCurrency("$%s", 12)).toBe("$12.00");
        expect(formatCurrency("$%s", 12.005)).toBe("$12.01");
        expect(formatCurrency("$%s", 0)).toBe("$0.00");
    });

    it("treats a missing amount as zero", () => {
        expect(formatCurrency("$%s", null)).toBe("$0.00");
        expect(formatCurrency("$%s", undefined)).toBe("$0.00");
    });

    it("accepts a numeric string, as the price maps from the server do", () => {
        expect(formatCurrency("$%s", "12.5")).toBe("$12.50");
    });

    it("falls back to the bare amount when the store ships no format", () => {
        expect(formatCurrency("", 12.5)).toBe("12.50");
        expect(formatCurrency(null, 12.5)).toBe("12.50");
        expect(formatCurrency(undefined, 12.5)).toBe("12.50");
        expect(DEFAULT_CURRENCY_FORMAT).toBe("%s");
    });

    it("replaces only the first placeholder, like Magento's own format", () => {
        expect(formatCurrency("%s of %s", 3)).toBe("3.00 of %s");
    });
});
