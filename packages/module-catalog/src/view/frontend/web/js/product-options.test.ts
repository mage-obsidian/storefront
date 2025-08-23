import { describe, it, expect, beforeEach } from "vitest";
import { createProductOptions } from "./product-options";

// Shared custom-options logic: live price delta from the embedded JSON config,
// accessible required validation, change subscription, and FormData collection
// (including the multi-value and value-less shapes). DOM-driven so it works the
// same under the simple form enhancer and the configurable island.

const config = {
    "1": { "11": { prices: { finalPrice: { amount: 5 } } }, "12": { prices: { finalPrice: { amount: 10 } } } },
    "2": { "21": { prices: { finalPrice: { amount: 2 } } }, "22": { prices: { finalPrice: { amount: 3 } } } },
    "3": { prices: { finalPrice: { amount: 7 } } },
};

function setup(): HTMLElement {
    document.body.innerHTML = `
        <div data-product-options>
            <script type="application/json" data-options-config>${JSON.stringify(config)}</script>
            <fieldset data-option data-option-id="1" data-option-type="drop_down" data-required>
                <select name="options[1]">
                    <option value="">--</option>
                    <option value="11">A</option>
                    <option value="12">B</option>
                </select>
                <p data-option-error hidden></p>
            </fieldset>
            <fieldset data-option data-option-id="2" data-option-type="checkbox">
                <input type="checkbox" name="options[2][]" value="21">
                <input type="checkbox" name="options[2][]" value="22">
                <p data-option-error hidden></p>
            </fieldset>
            <fieldset data-option data-option-id="3" data-option-type="field" data-required>
                <input type="text" name="options[3]">
                <p data-option-error hidden></p>
            </fieldset>
        </div>`;
    return document.querySelector("[data-product-options]") as HTMLElement;
}

beforeEach(() => {
    document.body.innerHTML = "";
});

describe("createProductOptions", () => {
    it("sums the live price delta across select and value-less options", () => {
        const root = setup();
        const opts = createProductOptions(root);
        expect(opts.delta()).toBe(0);

        (root.querySelector("select") as HTMLSelectElement).value = "12";
        expect(opts.delta()).toBe(10);

        (root.querySelector('input[value="21"]') as HTMLInputElement).checked = true;
        expect(opts.delta()).toBe(12);

        (root.querySelector('input[name="options[3]"]') as HTMLInputElement).value = "hello";
        expect(opts.delta()).toBe(19);
    });

    it("validates required options accessibly and passes once filled", () => {
        const root = setup();
        const opts = createProductOptions(root);

        expect(opts.validate()).toBe(false);
        const dropdownError = root.querySelector('[data-option-id="1"] [data-option-error]') as HTMLElement;
        const textError = root.querySelector('[data-option-id="3"] [data-option-error]') as HTMLElement;
        expect(dropdownError.hidden).toBe(false);
        expect(dropdownError.textContent).toContain("required");
        expect(textError.hidden).toBe(false);

        (root.querySelector("select") as HTMLSelectElement).value = "11";
        (root.querySelector('input[name="options[3]"]') as HTMLInputElement).value = "x";
        expect(opts.validate()).toBe(true);
        expect(dropdownError.hidden).toBe(true);
    });

    it("does not flag an optional unfilled option", () => {
        const root = setup();
        const opts = createProductOptions(root);
        opts.validate();
        const checkboxError = root.querySelector('[data-option-id="2"] [data-option-error]') as HTMLElement;
        expect(checkboxError.hidden).toBe(true);
    });

    it("collects selected option fields into a FormData with native names", () => {
        const root = setup();
        const opts = createProductOptions(root);
        (root.querySelector("select") as HTMLSelectElement).value = "12";
        (root.querySelector('input[value="21"]') as HTMLInputElement).checked = true;
        (root.querySelector('input[value="22"]') as HTMLInputElement).checked = true;
        (root.querySelector('input[name="options[3]"]') as HTMLInputElement).value = "engrave";

        const form = new FormData();
        opts.appendTo(form);

        expect(form.get("options[1]")).toBe("12");
        expect(form.getAll("options[2][]")).toEqual(["21", "22"]);
        expect(form.get("options[3]")).toBe("engrave");
    });

    it("notifies subscribers on change", () => {
        const root = setup();
        const opts = createProductOptions(root);
        let calls = 0;
        opts.onChange(() => { calls += 1; });

        (root.querySelector("select") as HTMLSelectElement).dispatchEvent(new Event("change", { bubbles: true }));
        expect(calls).toBe(1);
    });
});
