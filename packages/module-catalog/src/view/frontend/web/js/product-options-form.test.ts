import { describe, it, expect, beforeEach, vi } from "vitest";
import { setup } from "./product-options-form";
import { __formCalls, __reset, __setResult } from "MageObsidian_Storefront::js/useCart";

// Simple-product host for custom options: live total (base + deltas), required
// validation before the AJAX add, and toast feedback. The shared option logic is
// covered in product-options.test.ts; here we assert the form wiring.

const config = { "1": { "11": { prices: { finalPrice: { amount: 5 } } } } };

function buildForm({ required = false } = {}): HTMLFormElement {
    document.body.innerHTML = `
        <form data-product-form data-base-price="20" data-currency-format="$%s"
              data-msg-added="Added" data-msg-failed="Failed" action="/cart/add" method="post">
            <div data-product-options>
                <script type="application/json" data-options-config>${JSON.stringify(config)}</script>
                <fieldset data-option data-option-id="1" data-option-type="drop_down" ${required ? "data-required" : ""}>
                    <select name="options[1]">
                        <option value="">--</option>
                        <option value="11">A</option>
                    </select>
                    <p data-option-error hidden></p>
                </fieldset>
            </div>
            <span data-options-total></span>
            <button type="submit">Add</button>
        </form>`;
    return document.querySelector("form") as HTMLFormElement;
}

function submit(form: HTMLFormElement): Promise<void> {
    form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
    return Promise.resolve();
}

beforeEach(() => {
    __reset();
    document.body.innerHTML = "";
});

describe("product-options-form enhancer", () => {
    it("renders the live total from base price plus option deltas", () => {
        const form = buildForm();
        setup(form);
        const total = form.querySelector("[data-options-total]") as HTMLElement;
        expect(total.textContent).toBe("$20.00");

        const select = form.querySelector("select") as HTMLSelectElement;
        select.value = "11";
        select.dispatchEvent(new Event("change", { bubbles: true }));
        expect(total.textContent).toBe("$25.00");
    });

    it("blocks the add when a required option is empty", async () => {
        const form = buildForm({ required: true });
        setup(form);

        await submit(form);

        expect(__formCalls).toHaveLength(0);
        const error = form.querySelector("[data-option-error]") as HTMLElement;
        expect(error.hidden).toBe(false);
    });

    it("adds via the form and announces success once valid", async () => {
        const toast = vi.fn();
        window.addEventListener("obsidian:toast", toast);
        __setResult(true);
        const form = buildForm({ required: true });
        setup(form);
        (form.querySelector("select") as HTMLSelectElement).value = "11";

        await submit(form);
        await Promise.resolve();

        expect(__formCalls).toHaveLength(1);
        expect(__formCalls[0]).toBe(form);
        expect(toast.mock.calls.at(-1)?.[0].detail.message).toBe("Added");
        window.removeEventListener("obsidian:toast", toast);
    });

    // A file option is the most common source of a server-side rejection, and
    // Magento's AJAX cart answers 200 either way — so the toast has to relay the
    // reason instead of the generic fallback copy.
    it("announces the server's own error message when the add is rejected", async () => {
        const toast = vi.fn();
        window.addEventListener("obsidian:toast", toast);
        __setResult(false, "The file you uploaded has an invalid extension.");
        const form = buildForm();
        setup(form);

        await submit(form);
        await Promise.resolve();

        const detail = toast.mock.calls.at(-1)?.[0].detail;
        expect(detail.message).toBe("The file you uploaded has an invalid extension.");
        expect(detail.tone).toBe("error");
        window.removeEventListener("obsidian:toast", toast);
    });
});
