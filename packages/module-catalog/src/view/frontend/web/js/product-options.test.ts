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
    "4": { prices: { finalPrice: { amount: 10 } } },
};

/** Put a real File on an input, the way choosing one in the dialog would. */
function chooseFile(input: HTMLInputElement, name = "art.png"): void {
    const transfer = new DataTransfer();
    transfer.items.add(new File(["binary"], name, { type: "image/png" }));
    input.files = transfer.files;
}

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
            <fieldset data-option data-option-id="4" data-option-type="file" data-required>
                <input type="file" name="options_4_file">
                <input type="hidden" name="options_4_file_action" value="save_new">
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
        chooseFile(root.querySelector('input[type="file"]') as HTMLInputElement);
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

    // The file option is the only one whose value lives in a FileList rather than
    // in `value`, so delta/validate/appendTo each need their own branch for it.
    describe("file options", () => {
        it("adds the option price to the delta once a file is chosen", () => {
            const root = setup();
            const opts = createProductOptions(root);
            const input = root.querySelector('input[type="file"]') as HTMLInputElement;
            expect(opts.delta()).toBe(0);

            chooseFile(input);

            expect(opts.delta()).toBe(10);
        });

        it("flags a required file option while empty", () => {
            const root = setup();
            const opts = createProductOptions(root);

            expect(opts.validate()).toBe(false);
            const error = root.querySelector('[data-option-id="4"] [data-option-error]') as HTMLElement;
            expect(error.hidden).toBe(false);

            chooseFile(root.querySelector('input[type="file"]') as HTMLInputElement);
            opts.validate();
            expect(error.hidden).toBe(true);
        });

        it("appends the chosen File under Magento's native field name", () => {
            const root = setup();
            const opts = createProductOptions(root);
            chooseFile(root.querySelector('input[type="file"]') as HTMLInputElement, "logo.png");

            const form = new FormData();
            opts.appendTo(form);

            const file = form.get("options_4_file") as File;
            expect(file).toBeInstanceOf(File);
            expect(file.name).toBe("logo.png");
            expect(form.get("options_4_file_action")).toBe("save_new");
        });

        it("skips an empty file input so Magento does not see a blank upload", () => {
            const root = setup();
            const opts = createProductOptions(root);

            const form = new FormData();
            opts.appendTo(form);

            expect(form.get("options_4_file")).toBeNull();
        });
    });

    // Reconfiguring a cart line: the template marks the fieldset as already
    // holding an upload and ships `save_old`, which is how Magento restores the
    // previous file. Picking a new one has to flip that to `save_new`, otherwise
    // the upload is silently discarded in favour of the old file.
    describe("file options with a previously uploaded file", () => {
        function setupUploaded(): HTMLElement {
            document.body.innerHTML = `
                <div data-product-options>
                    <script type="application/json" data-options-config>${JSON.stringify(config)}</script>
                    <fieldset data-option data-option-id="4" data-option-type="file" data-required data-option-uploaded>
                        <input type="file" name="options_4_file">
                        <input type="hidden" name="options_4_file_action" value="save_old">
                        <p data-option-error hidden></p>
                    </fieldset>
                </div>`;
            return document.querySelector("[data-product-options]") as HTMLElement;
        }

        it("counts the kept file as filled, so a required option validates", () => {
            const root = setupUploaded();
            const opts = createProductOptions(root);

            expect(opts.validate()).toBe(true);
            expect(opts.delta()).toBe(10);
        });

        it("keeps save_old when no new file is chosen", () => {
            const root = setupUploaded();
            const opts = createProductOptions(root);

            const form = new FormData();
            opts.appendTo(form);

            expect(form.get("options_4_file_action")).toBe("save_old");
            expect(form.get("options_4_file")).toBeNull();
        });

        it("switches to save_new when the shopper picks a replacement", () => {
            const root = setupUploaded();
            const opts = createProductOptions(root);
            const input = root.querySelector('input[type="file"]') as HTMLInputElement;

            chooseFile(input, "new.png");
            input.dispatchEvent(new Event("change", { bubbles: true }));

            const form = new FormData();
            opts.appendTo(form);

            expect(form.get("options_4_file_action")).toBe("save_new");
            expect((form.get("options_4_file") as File).name).toBe("new.png");
        });
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
