// This file is part of the MageObsidian - Checkout project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT

export type TaxMode = "incl" | "excl" | "both";
export type FixedTaxMode = "off" | "incl" | "incl_desc" | "excl_desc_incl" | "excl";
export type TaxBasis = "incl" | "excl";

export interface TaxDisplayConfig {
    price: TaxMode;
    subtotal: TaxMode;
    shipping: TaxMode;
    fullSummary: boolean;
    zeroTax: boolean;
    fixedTax: FixedTaxMode;
    fixedTaxInSubtotal: boolean;
}

export const DEFAULT_TAX_DISPLAY: TaxDisplayConfig = {
    price: "excl",
    subtotal: "excl",
    shipping: "excl",
    fullSummary: false,
    zeroTax: false,
    fixedTax: "off",
    fixedTaxInSubtotal: false,
};

export interface TaxRate {
    percent?: string | number;
    title?: string;
}

export interface TaxDetail {
    amount: number;
    rates?: TaxRate[];
}

export interface TotalsSegment {
    code: string;
    title?: string;
    value: number | null;
    extension_attributes?: { tax_grandtotal_details?: TaxDetail[] };
}

export interface TotalsItem {
    weee_tax_applied?: string | null;
}

export interface TaxTotals {
    subtotal?: number;
    subtotal_incl_tax?: number;
    shipping_amount?: number;
    shipping_incl_tax?: number;
    total_segments?: TotalsSegment[];
    items?: TotalsItem[];
}

export interface TotalRow {
    code: string;
    label: string;
    amount: number | null;
    detail: boolean;
}

export type TaxLabels = Partial<
    Record<"subtotalExcl" | "subtotalIncl" | "shippingExcl" | "shippingIncl" | "fixedTax" | "exclTax" | "inclTax", string>
>;

export interface RateAmounts {
    amount?: number;
    price_excl_tax?: number;
    price_incl_tax?: number;
}

export interface RateAmount {
    basis: TaxBasis;
    amount: number;
}

const MODES: TaxMode[] = ["incl", "excl", "both"];
const FIXED_MODES: FixedTaxMode[] = ["off", "incl", "incl_desc", "excl_desc_incl", "excl"];

const modeOf = (value: unknown, fallback: TaxMode): TaxMode =>
    MODES.includes(value as TaxMode) ? (value as TaxMode) : fallback;

export function normalizeTaxDisplay(raw: unknown): TaxDisplayConfig {
    const source = (raw && typeof raw === "object" ? raw : {}) as Record<string, unknown>;
    return {
        price: modeOf(source.price, DEFAULT_TAX_DISPLAY.price),
        subtotal: modeOf(source.subtotal, DEFAULT_TAX_DISPLAY.subtotal),
        shipping: modeOf(source.shipping, DEFAULT_TAX_DISPLAY.shipping),
        fullSummary: source.fullSummary === true,
        zeroTax: source.zeroTax === true,
        fixedTax: FIXED_MODES.includes(source.fixedTax as FixedTaxMode)
            ? (source.fixedTax as FixedTaxMode)
            : DEFAULT_TAX_DISPLAY.fixedTax,
        fixedTaxInSubtotal: source.fixedTaxInSubtotal === true,
    };
}

export function basisLabel(basis: TaxBasis, labels: TaxLabels = {}): string {
    return basis === "incl" ? (labels.inclTax ?? "Incl. Tax") : (labels.exclTax ?? "Excl. Tax");
}

export function rateAmounts(rate: RateAmounts, mode: TaxMode): RateAmount[] {
    const excl = rate.price_excl_tax ?? rate.amount ?? 0;
    const incl = rate.price_incl_tax ?? rate.amount ?? excl;
    if (mode === "both") {
        return [
            { basis: "excl", amount: excl },
            { basis: "incl", amount: incl },
        ];
    }
    return [mode === "incl" ? { basis: "incl", amount: incl } : { basis: "excl", amount: excl }];
}

function fill(template: string, values: Record<string, string>): string {
    return Object.entries(values).reduce((text, [key, value]) => text.replace(`{${key}}`, value), template);
}

function shippingDescription(title: string | undefined): string {
    return /\((.*)\)\s*$/.exec(title ?? "")?.[1] ?? "";
}

function fixedTaxTotals(items: TotalsItem[] | undefined): Array<{ label: string; amount: number }> {
    const totals = new Map<string, number>();
    for (const item of items ?? []) {
        if (!item.weee_tax_applied) {
            continue;
        }
        let applied: unknown;
        try {
            applied = JSON.parse(item.weee_tax_applied);
        } catch {
            continue;
        }
        for (const entry of Array.isArray(applied) ? applied : []) {
            const label = String(entry?.title ?? "");
            totals.set(label, (totals.get(label) ?? 0) + Number(entry?.row_amount ?? 0));
        }
    }
    return [...totals.entries()].map(([label, amount]) => ({ label, amount }));
}

const row = (code: string, label: string, amount: number | null, detail = false): TotalRow => ({
    code,
    label,
    amount,
    detail,
});

function subtotalRows(
    segment: TotalsSegment,
    totals: TaxTotals,
    config: TaxDisplayConfig,
    labels: TaxLabels,
    fixedTaxes: Array<{ label: string; amount: number }>,
): TotalRow[] {
    const value = segment.value ?? 0;
    const excl = totals.subtotal ?? value;
    const incl = totals.subtotal_incl_tax ?? value;
    const title = segment.title ?? "";

    if (config.subtotal === "both") {
        return [
            row("subtotal_excl_tax", labels.subtotalExcl ?? "Subtotal (Excl. Tax)", excl),
            row("subtotal_incl_tax", labels.subtotalIncl ?? "Subtotal (Incl. Tax)", incl),
        ];
    }
    if (config.subtotal === "incl") {
        return [row("subtotal", title, incl)];
    }

    const included = config.fixedTax === "incl" && !config.fixedTaxInSubtotal;
    const fixed = included ? fixedTaxes.reduce((sum, entry) => sum + entry.amount, 0) : 0;
    return [row("subtotal", title, excl + fixed)];
}

function shippingRows(segment: TotalsSegment, totals: TaxTotals, config: TaxDisplayConfig, labels: TaxLabels): TotalRow[] {
    const excl = totals.shipping_amount ?? segment.value ?? 0;
    const incl = totals.shipping_incl_tax ?? excl;
    const description = shippingDescription(segment.title);

    if (config.shipping === "both") {
        return [
            row("shipping_excl_tax", fill(labels.shippingExcl ?? "Shipping Excl. Tax ({description})", { description }), excl),
            row("shipping_incl_tax", fill(labels.shippingIncl ?? "Shipping Incl. Tax ({description})", { description }), incl),
        ];
    }
    return [row("shipping", segment.title ?? "", config.shipping === "incl" ? incl : excl)];
}

function taxDetailRows(detail: TaxDetail): TotalRow[] {
    const rates = detail.rates ?? [];
    const percentSum = rates.reduce((sum, rate) => sum + Number(rate.percent ?? 0), 0);
    let remaining = detail.amount;

    return rates.map((rate, index) => {
        const isLast = index === rates.length - 1;
        const share =
            isLast || percentSum <= 0
                ? remaining
                : Math.round((detail.amount * Number(rate.percent ?? 0) * 100) / percentSum) / 100;
        remaining = Math.round((remaining - share) * 100) / 100;
        const percent = rate.percent === undefined ? "" : ` (${Number(rate.percent)}%)`;
        return row("tax_detail", `${rate.title ?? ""}${percent}`, share, true);
    });
}

function taxRows(segment: TotalsSegment, config: TaxDisplayConfig): TotalRow[] {
    const amount = segment.value ?? 0;
    if (amount === 0 && !config.zeroTax) {
        return [];
    }
    const rows = [row("tax", segment.title ?? "", amount)];
    if (amount === 0 || !config.fullSummary) {
        return rows;
    }
    for (const detail of segment.extension_attributes?.tax_grandtotal_details ?? []) {
        rows.push(...taxDetailRows(detail));
    }
    return rows;
}

function fixedTaxRows(
    fixedTaxes: Array<{ label: string; amount: number }>,
    config: TaxDisplayConfig,
    labels: TaxLabels,
): TotalRow[] {
    if (
        fixedTaxes.length === 0 ||
        config.subtotal !== "excl" ||
        config.fixedTaxInSubtotal ||
        config.fixedTax === "off" ||
        config.fixedTax === "incl"
    ) {
        return [];
    }
    if (config.fixedTax === "excl") {
        return [
            row(
                "fixed_tax",
                labels.fixedTax ?? "Fixed Product Tax",
                fixedTaxes.reduce((sum, entry) => sum + entry.amount, 0),
            ),
        ];
    }
    return fixedTaxes.map((entry) => row("fixed_tax", entry.label, entry.amount));
}

export function buildTotalRows(totals: TaxTotals, config: TaxDisplayConfig, labels: TaxLabels = {}): TotalRow[] {
    const fixedTaxes = fixedTaxTotals(totals.items);
    const fixedRows = fixedTaxRows(fixedTaxes, config, labels);
    const rows: TotalRow[] = [];
    let fixedPlaced = false;

    for (const segment of totals.total_segments ?? []) {
        if (!fixedPlaced && (segment.code === "tax" || segment.code === "grand_total")) {
            rows.push(...fixedRows);
            fixedPlaced = true;
        }
        if (segment.code === "subtotal") {
            rows.push(...subtotalRows(segment, totals, config, labels, fixedTaxes));
        } else if (segment.code === "shipping") {
            rows.push(...shippingRows(segment, totals, config, labels));
        } else if (segment.code === "tax") {
            rows.push(...taxRows(segment, config));
        } else if (segment.code !== "weee" && segment.code !== "weee_tax") {
            rows.push(row(segment.code, segment.title ?? "", segment.value));
        }
    }
    if (!fixedPlaced) {
        rows.push(...fixedRows);
    }

    return rows;
}
