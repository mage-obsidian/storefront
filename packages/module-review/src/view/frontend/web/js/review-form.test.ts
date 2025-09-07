import { describe, it, expect, beforeEach } from "vitest";
import { findInvalid, setup } from "./review-form";

// Progressive review-form enhancer: accessible inline validation replacing the
// native bubble (sr-only required radios can't host it). The form still POSTs
// natively without JS.

function buildForm(): HTMLFormElement {
    document.body.innerHTML = `
        <form data-review-form action="/review/product/post" method="post">
            <fieldset role="radiogroup" aria-label="Quality">
                <input type="radio" name="ratings[1]" value="5" id="q5" required>
                <label for="q5">5</label>
                <input type="radio" name="ratings[1]" value="4" id="q4" required>
                <label for="q4">4</label>
            </fieldset>
            <input name="nickname" type="text" required>
            <input name="title" type="text" required>
            <textarea name="detail" required></textarea>
            <button type="submit">Submit</button>
        </form>`;
    return document.querySelector("form") as HTMLFormElement;
}

beforeEach(() => {
    document.body.innerHTML = "";
});

describe("review-form enhancer", () => {
    it("flags an empty required text field first", () => {
        const form = buildForm();
        (form.querySelector('[name="ratings[1]"]') as HTMLInputElement).checked = true;
        const invalid = findInvalid(form);
        expect(invalid?.getAttribute("name")).toBe("nickname");
    });

    it("flags a rating group with no selection", () => {
        const form = buildForm();
        (form.querySelector('[name="nickname"]') as HTMLInputElement).value = "Ada";
        (form.querySelector('[name="title"]') as HTMLInputElement).value = "Great";
        (form.querySelector('[name="detail"]') as HTMLTextAreaElement).value = "Loved it";
        const invalid = findInvalid(form);
        expect(invalid?.getAttribute("role")).toBe("radiogroup");
    });

    it("passes once a rating and all fields are filled", () => {
        const form = buildForm();
        (form.querySelector('[name="ratings[1]"]') as HTMLInputElement).checked = true;
        (form.querySelector('[name="nickname"]') as HTMLInputElement).value = "Ada";
        (form.querySelector('[name="title"]') as HTMLInputElement).value = "Great";
        (form.querySelector('[name="detail"]') as HTMLTextAreaElement).value = "Loved it";
        expect(findInvalid(form)).toBeNull();
    });

    it("blocks submit and marks the invalid control with aria-invalid", () => {
        const form = buildForm();
        setup(form);
        let defaultPrevented = false;
        form.addEventListener("submit", (e) => { defaultPrevented = e.defaultPrevented; });
        form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
        expect(defaultPrevented).toBe(true);
        expect(form.querySelector('[aria-invalid="true"]')).not.toBeNull();
    });
});
