/**
 * Bundle live-total logic, framework-free and DOM-driven. It reads the native
 * bundle option controls (bundle_option[...] / bundle_option_qty[...]) plus the
 * embedded price config (Magento's getJsonConfig) and exposes the running total
 * and a change subscription. The controls POST natively without JS; this only
 * estimates the total live — the cart computes the authoritative one.
 */

interface SelectionNode {
    qty?: number | string;
    prices?: { finalPrice?: { amount?: number | string } };
}

interface OptionNode {
    selections?: Record<string, SelectionNode>;
    isMulti?: boolean;
}

export interface BundleConfig {
    options?: Record<string, OptionNode>;
    prices?: { finalPrice?: { amount?: number | string } };
}

export interface BundleSelections {
    chosen: Record<string, string[]>;
    qtys: Record<string, number>;
}

export interface BundlePrice {
    total: () => number;
    onChange: (cb: () => void) => void;
}

function toNumber(raw: number | string | undefined): number {
    const value = Number(raw ?? 0);
    return Number.isFinite(value) ? value : 0;
}

/**
 * Pull the selected selection ids (per option) and any editable quantities out
 * of a bundle form. Selects carry their value(s); radios/checkboxes their
 * checked state; a single required option may be a hidden input.
 */
export function readSelections(form: HTMLElement): BundleSelections {
    const chosen: Record<string, string[]> = {};
    const qtys: Record<string, number> = {};
    const controls = form.querySelectorAll<HTMLInputElement | HTMLSelectElement>('[name*="bundle_option"]');

    controls.forEach((el) => {
        const name = el.getAttribute("name") ?? "";
        const qtyMatch = name.match(/^bundle_option_qty\[(\d+)]/);
        if (qtyMatch) {
            qtys[qtyMatch[1]] = toNumber((el as HTMLInputElement).value);
            return;
        }
        const optMatch = name.match(/^bundle_option\[(\d+)]/);
        if (!optMatch) {
            return;
        }
        const optId = optMatch[1];
        chosen[optId] ??= [];

        if (el.tagName.toLowerCase() === "select") {
            const select = el as HTMLSelectElement;
            const values = select.multiple
                ? Array.from(select.selectedOptions).map((o) => o.value)
                : [select.value];
            chosen[optId] = values.filter((v) => v !== "");
            return;
        }

        const input = el as HTMLInputElement;
        if (input.type === "radio" || input.type === "checkbox") {
            if (input.checked && input.value !== "") {
                chosen[optId].push(input.value);
            }
        } else if (input.value !== "") {
            chosen[optId].push(input.value);
        }
    });

    return { chosen, qtys };
}

/**
 * Sum the base price and every selected selection's final price times its
 * quantity (the editable qty input when present, else the selection default).
 */
export function computeTotal(config: BundleConfig, selections: BundleSelections): number {
    const options = config.options ?? {};
    let total = toNumber(config.prices?.finalPrice?.amount);

    for (const [optId, selectionIds] of Object.entries(selections.chosen)) {
        const option = options[optId];
        if (!option?.selections) {
            continue;
        }
        for (const selectionId of selectionIds) {
            const selection = option.selections[selectionId];
            if (!selection) {
                continue;
            }
            const price = toNumber(selection.prices?.finalPrice?.amount);
            const qty = optId in selections.qtys ? selections.qtys[optId] : toNumber(selection.qty);
            total += price * qty;
        }
    }

    return total;
}

/**
 * Wire a bundle form to its config: total() recomputes on demand, onChange fires
 * on every control change.
 */
export function createBundlePrice(form: HTMLElement, config: BundleConfig): BundlePrice {
    return {
        total: () => computeTotal(config, readSelections(form)),
        onChange: (cb: () => void) => {
            form.addEventListener("change", cb);
            form.addEventListener("input", cb);
        },
    };
}
