// This file is part of the MageObsidian - Checkout project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, expect, it } from "vitest";
import {
    DEFAULT_TAX_DISPLAY,
    buildTotalRows,
    normalizeTaxDisplay,
    rateAmounts,
    type TaxDisplayConfig,
    type TaxTotals,
} from "./tax-display.ts";

const config = (overrides: Partial<TaxDisplayConfig> = {}): TaxDisplayConfig => ({ ...DEFAULT_TAX_DISPLAY, ...overrides });

const totals = (overrides: Partial<TaxTotals> = {}): TaxTotals => ({
    subtotal: 32,
    subtotal_incl_tax: 34.56,
    shipping_amount: 5,
    shipping_incl_tax: 5.4,
    total_segments: [
        { code: "subtotal", title: "Subtotal", value: 34.56 },
        { code: "shipping", title: "Shipping & Handling (Flat Rate - Fixed)", value: 5 },
        {
            code: "tax",
            title: "Tax",
            value: 2.96,
            extension_attributes: {
                tax_grandtotal_details: [
                    {
                        amount: 2.96,
                        rates: [
                            { percent: "6", title: "State" },
                            { percent: "2", title: "County" },
                        ],
                    },
                ],
            },
        },
        { code: "grand_total", title: "Grand Total", value: 39.96 },
    ],
    ...overrides,
});

const summary = (rows: ReturnType<typeof buildTotalRows>) => rows.map((r) => [r.code, r.label, r.amount]);

describe("normalizeTaxDisplay", () => {
    it("falls back to excluding tax when nothing was configured", () => {
        expect(normalizeTaxDisplay(undefined)).toEqual(DEFAULT_TAX_DISPLAY);
        expect(normalizeTaxDisplay({ price: "nonsense", fixedTax: "nonsense" })).toEqual(DEFAULT_TAX_DISPLAY);
    });

    it("keeps what the server sent", () => {
        expect(
            normalizeTaxDisplay({
                price: "both",
                subtotal: "incl",
                shipping: "both",
                fullSummary: true,
                zeroTax: true,
                fixedTax: "incl_desc",
                fixedTaxInSubtotal: true,
            }),
        ).toEqual({
            price: "both",
            subtotal: "incl",
            shipping: "both",
            fullSummary: true,
            zeroTax: true,
            fixedTax: "incl_desc",
            fixedTaxInSubtotal: true,
        });
    });
});

describe("rateAmounts", () => {
    const rate = { amount: 5, price_excl_tax: 5, price_incl_tax: 5.4 };

    it("picks the amount the mode shows", () => {
        expect(rateAmounts(rate, "excl")).toEqual([{ basis: "excl", amount: 5 }]);
        expect(rateAmounts(rate, "incl")).toEqual([{ basis: "incl", amount: 5.4 }]);
        expect(rateAmounts(rate, "both")).toEqual([
            { basis: "excl", amount: 5 },
            { basis: "incl", amount: 5.4 },
        ]);
    });

    it("uses the plain amount when the rate carries no tax split", () => {
        expect(rateAmounts({ amount: 7 }, "both")).toEqual([
            { basis: "excl", amount: 7 },
            { basis: "incl", amount: 7 },
        ]);
    });
});

describe("buildTotalRows", () => {
    it("states the subtotal and the shipping without tax by default", () => {
        const rows = buildTotalRows(totals(), config());

        expect(summary(rows).slice(0, 2)).toEqual([
            ["subtotal", "Subtotal", 32],
            ["shipping", "Shipping & Handling (Flat Rate - Fixed)", 5],
        ]);
    });

    it("states them with tax when the store includes it", () => {
        const rows = buildTotalRows(totals(), config({ subtotal: "incl", shipping: "incl" }));

        expect(summary(rows).slice(0, 2)).toEqual([
            ["subtotal", "Subtotal", 34.56],
            ["shipping", "Shipping & Handling (Flat Rate - Fixed)", 5.4],
        ]);
    });

    it("states both amounts when the store asks for both", () => {
        const rows = buildTotalRows(totals(), config({ subtotal: "both", shipping: "both" }));

        expect(summary(rows).slice(0, 4)).toEqual([
            ["subtotal_excl_tax", "Subtotal (Excl. Tax)", 32],
            ["subtotal_incl_tax", "Subtotal (Incl. Tax)", 34.56],
            ["shipping_excl_tax", "Shipping Excl. Tax (Flat Rate - Fixed)", 5],
            ["shipping_incl_tax", "Shipping Incl. Tax (Flat Rate - Fixed)", 5.4],
        ]);
    });

    it("translates the labels it is given", () => {
        const rows = buildTotalRows(totals(), config({ shipping: "both" }), {
            shippingExcl: "Envío sin impuestos ({description})",
        });

        expect(rows[1].label).toBe("Envío sin impuestos (Flat Rate - Fixed)");
    });

    it("lists a single tax row without the full summary", () => {
        const rows = buildTotalRows(totals(), config({ fullSummary: false }));

        expect(rows.filter((r) => r.code.startsWith("tax"))).toEqual([
            { code: "tax", label: "Tax", amount: 2.96, detail: false },
        ]);
    });

    it("itemises every tax of the full summary", () => {
        const rows = buildTotalRows(totals(), config({ fullSummary: true }));

        expect(rows.filter((r) => r.code === "tax_detail")).toEqual([
            { code: "tax_detail", label: "State (6%)", amount: 2.22, detail: true },
            { code: "tax_detail", label: "County (2%)", amount: 0.74, detail: true },
        ]);
    });

    it("leaves out a tax of zero unless the store shows it", () => {
        const zero = totals({
            total_segments: [
                { code: "subtotal", title: "Subtotal", value: 32 },
                { code: "tax", title: "Tax", value: 0 },
                { code: "grand_total", title: "Grand Total", value: 32 },
            ],
        });

        expect(buildTotalRows(zero, config()).map((r) => r.code)).toEqual(["subtotal", "grand_total"]);
        expect(buildTotalRows(zero, config({ zeroTax: true, fullSummary: true })).map((r) => r.code)).toEqual([
            "subtotal",
            "tax",
            "grand_total",
        ]);
    });

    it("keeps the other segments as the server sent them", () => {
        const discounted = totals({
            total_segments: [
                { code: "discount", title: "Discount", value: -3 },
                { code: "grand_total", title: "Grand Total", value: 29 },
            ],
        });

        expect(summary(buildTotalRows(discounted, config()))).toEqual([
            ["discount", "Discount", -3],
            ["grand_total", "Grand Total", 29],
        ]);
    });

    describe("fixed product tax", () => {
        const applied = JSON.stringify([
            { title: "Recycling", row_amount: 3 },
            { title: "Battery", row_amount: 2 },
        ]);
        const withFixedTax = (): TaxTotals => {
            const base = totals();
            return {
                ...base,
                total_segments: [
                    ...(base.total_segments ?? []).slice(0, 2),
                    { code: "weee_tax", title: "FPT", value: 5 },
                    ...(base.total_segments ?? []).slice(2),
                ],
                items: [{ weee_tax_applied: applied }, { weee_tax_applied: applied }],
            };
        };
        const fixedRows = (rows: ReturnType<typeof buildTotalRows>) => rows.filter((r) => r.code === "fixed_tax");

        it("names each fixed tax when the store describes them", () => {
            const rows = buildTotalRows(withFixedTax(), config({ fixedTax: "incl_desc" }));

            expect(summary(fixedRows(rows))).toEqual([
                ["fixed_tax", "Recycling", 6],
                ["fixed_tax", "Battery", 4],
            ]);
            expect(rows.map((r) => r.code).indexOf("fixed_tax")).toBeLessThan(rows.map((r) => r.code).indexOf("tax"));
        });

        it("adds no row of its own when the price includes it", () => {
            const rows = buildTotalRows(withFixedTax(), config({ fixedTax: "incl" }));

            expect(fixedRows(rows)).toEqual([]);
            expect(rows[0].amount).toBe(42);
        });

        it("states one row when the store excludes it", () => {
            expect(summary(fixedRows(buildTotalRows(withFixedTax(), config({ fixedTax: "excl" }))))).toEqual([
                ["fixed_tax", "Fixed Product Tax", 10],
            ]);
        });

        it("states nothing when the subtotal already carries it", () => {
            expect(fixedRows(buildTotalRows(withFixedTax(), config({ fixedTax: "incl_desc", fixedTaxInSubtotal: true })))).toEqual([]);
            expect(fixedRows(buildTotalRows(withFixedTax(), config({ fixedTax: "incl_desc", subtotal: "incl" })))).toEqual([]);
        });

        it("drops the core fixed tax segment in favour of its own rows", () => {
            const rows = buildTotalRows(withFixedTax(), config({ fixedTax: "off" }));

            expect(rows.map((r) => r.code)).not.toContain("weee_tax");
        });

        it("ignores an applied list it cannot read", () => {
            const broken = { ...withFixedTax(), items: [{ weee_tax_applied: "{" }, { weee_tax_applied: null }, {}] };

            expect(fixedRows(buildTotalRows(broken, config({ fixedTax: "incl_desc" })))).toEqual([]);
        });
    });
});
