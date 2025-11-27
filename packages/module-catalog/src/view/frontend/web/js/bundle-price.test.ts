import { describe, it, expect } from "vitest";
import { computeTotal, readSelections, createBundlePrice, type BundleConfig } from "./bundle-price";

const config: BundleConfig = {
    prices: { finalPrice: { amount: 0 } },
    options: {
        "1": {
            isMulti: false,
            selections: {
                "10": { qty: 1, prices: { finalPrice: { amount: 23 } } },
                "11": { qty: 1, prices: { finalPrice: { amount: 30 } } },
            },
        },
        "2": {
            isMulti: true,
            selections: {
                "20": { qty: 2, prices: { finalPrice: { amount: 5 } } },
                "21": { qty: 1, prices: { finalPrice: { amount: 7 } } },
            },
        },
    },
};

function form(html: string): HTMLElement {
    const el = document.createElement("form");
    el.innerHTML = html;
    return el;
}

describe("readSelections", () => {
    it("reads a dropdown value and ignores the empty option", () => {
        const { chosen } = readSelections(form(`
            <select name="bundle_option[1]"><option value="">--</option><option value="10" selected>A</option></select>
        `));
        expect(chosen["1"]).toEqual(["10"]);
    });

    it("collects checked checkboxes and skips unchecked", () => {
        const { chosen } = readSelections(form(`
            <input type="checkbox" name="bundle_option[2][]" value="20" checked>
            <input type="checkbox" name="bundle_option[2][]" value="21">
        `));
        expect(chosen["2"]).toEqual(["20"]);
    });

    it("captures an editable quantity", () => {
        const { qtys } = readSelections(form(`
            <select name="bundle_option[1]"><option value="10" selected>A</option></select>
            <input name="bundle_option_qty[1]" value="3">
        `));
        expect(qtys["1"]).toBe(3);
    });
});

describe("computeTotal", () => {
    it("is the base price when nothing is selected", () => {
        expect(computeTotal(config, { chosen: {}, qtys: {} })).toBe(0);
    });

    it("uses the selection default quantity", () => {
        // option 2, selection 20: amount 5 * default qty 2 = 10
        expect(computeTotal(config, { chosen: { "2": ["20"] }, qtys: {} })).toBe(10);
    });

    it("prefers an editable quantity over the default", () => {
        // option 1, selection 10: amount 23 * editable qty 3 = 69
        expect(computeTotal(config, { chosen: { "1": ["10"] }, qtys: { "1": 3 } })).toBe(69);
    });

    it("sums multiple options", () => {
        // 23 (1x10) + 5*2 (2x20) + 7 (2x21) = 40
        expect(computeTotal(config, { chosen: { "1": ["10"], "2": ["20", "21"] }, qtys: {} })).toBe(40);
    });
});

describe("createBundlePrice", () => {
    it("recomputes the total from the live form on change", () => {
        const el = form(`
            <select name="bundle_option[1]"><option value="">--</option><option value="10">A</option><option value="11">B</option></select>
        `);
        const bundle = createBundlePrice(el, config);
        expect(bundle.total()).toBe(0);

        let fired = 0;
        bundle.onChange(() => (fired += 1));
        const select = el.querySelector("select") as HTMLSelectElement;
        select.value = "11";
        select.dispatchEvent(new Event("change", { bubbles: true }));

        expect(fired).toBe(1);
        expect(bundle.total()).toBe(30);
    });
});
