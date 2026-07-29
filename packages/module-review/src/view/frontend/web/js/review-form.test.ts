import { describe, it, expect, beforeEach, vi } from "vitest";
import { findUnratedGroup, setup } from "./review-form";

// Progressive review-form enhancer: the text fields run through the shared
// validation engine, the rating groups are checked here. The form still POSTs
// natively without JS.

function buildForm(): HTMLFormElement {
    document.body.innerHTML = `
        <form data-review-form action="/review/product/post" method="post" data-err-required="Required.">
            <fieldset role="radiogroup" aria-label="Quality">
                <input type="radio" name="ratings[1]" value="5" id="q5" required>
                <label for="q5">5</label>
                <input type="radio" name="ratings[1]" value="4" id="q4" required>
                <label for="q4">4</label>
            </fieldset>
            <div class="field">
                <input name="nickname" id="review-nickname" type="text" required aria-describedby="review-nickname-error">
                <p class="field__error" id="review-nickname-error" role="alert"></p>
            </div>
            <div class="field">
                <input name="title" id="review-title" type="text" required aria-describedby="review-title-error">
                <p class="field__error" id="review-title-error" role="alert"></p>
            </div>
            <div class="field">
                <textarea name="detail" id="review-detail" required aria-describedby="review-detail-error"></textarea>
                <p class="field__error" id="review-detail-error" role="alert"></p>
            </div>
            <button type="submit">Submit</button>
        </form>`;
    return document.querySelector("form") as HTMLFormElement;
}

function fillText(form: HTMLFormElement): void {
    (form.querySelector('[name="nickname"]') as HTMLInputElement).value = "Ada";
    (form.querySelector('[name="title"]') as HTMLInputElement).value = "Great";
    (form.querySelector('[name="detail"]') as HTMLTextAreaElement).value = "Loved it";
}

function submit(form: HTMLFormElement): boolean {
    return !form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
}

beforeEach(() => {
    document.body.innerHTML = "";
});

describe("review-form enhancer", () => {
    it("finds the rating group with no selection", () => {
        const form = buildForm();
        expect(findUnratedGroup(form)?.getAttribute("role")).toBe("radiogroup");
    });

    it("finds no unrated group once a star is picked", () => {
        const form = buildForm();
        (form.querySelector('[name="ratings[1]"]') as HTMLInputElement).checked = true;
        expect(findUnratedGroup(form)).toBeNull();
    });

    it("writes the translated required message into the field's error node", () => {
        const form = buildForm();
        setup(form);
        submit(form);
        expect(document.getElementById("review-nickname-error")?.textContent).toBe("Required.");
        expect(form.querySelector('[name="nickname"]')?.getAttribute("aria-invalid")).toBe("true");
    });

    it("blocks the submit and flags the rating group when only the stars are missing", () => {
        const form = buildForm();
        form.submit = vi.fn();
        setup(form);
        fillText(form);

        expect(submit(form)).toBe(true);
        expect(form.querySelector('[role="radiogroup"]')?.getAttribute("aria-invalid")).toBe("true");
        expect(form.submit).not.toHaveBeenCalled();
    });

    it("posts natively once the stars and the fields are filled", () => {
        const form = buildForm();
        form.submit = vi.fn();
        setup(form);
        fillText(form);
        (form.querySelector('[name="ratings[1]"]') as HTMLInputElement).checked = true;

        submit(form);
        expect(form.submit).toHaveBeenCalledOnce();
    });

    it("clears the rating flag as soon as a star is picked", () => {
        const form = buildForm();
        form.submit = vi.fn();
        setup(form);
        fillText(form);
        submit(form);

        const radio = form.querySelector('[name="ratings[1]"]') as HTMLInputElement;
        radio.checked = true;
        radio.dispatchEvent(new Event("change", { bubbles: true }));

        expect(form.querySelector('[role="radiogroup"]')?.hasAttribute("aria-invalid")).toBe(false);
    });
});
