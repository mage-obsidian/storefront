/**
 * Progressive enhancement for the PDP review form. The form is a real POST to
 * review/product/post and works without JS (the star inputs are CSS-only). With
 * JS we replace native constraint validation — `required` on visually-hidden
 * (sr-only) rating radios can't reliably host the browser's validation bubble —
 * with an accessible inline check: the first unfilled field (or a rating group
 * with no selection) gets focus and aria-invalid, and submission is blocked.
 */

/** First invalid control: an empty required text field, or a ratingless group. */
export function findInvalid(form: HTMLFormElement): HTMLElement | null {
    const fields = form.querySelectorAll<HTMLInputElement | HTMLTextAreaElement>(
        "input[required], textarea[required]",
    );
    for (const field of fields) {
        if (field instanceof HTMLInputElement && field.type === "radio") {
            continue;
        }
        if (field.value.trim() === "") {
            return field;
        }
    }
    for (const group of form.querySelectorAll<HTMLElement>('[role="radiogroup"]')) {
        const chosen = Array.from(group.querySelectorAll<HTMLInputElement>('input[type="radio"]')).some(
            (radio) => radio.checked,
        );
        if (!chosen) {
            return group;
        }
    }
    return null;
}

function clearInvalid(form: HTMLFormElement): void {
    form.querySelectorAll("[aria-invalid]").forEach((el) => el.removeAttribute("aria-invalid"));
}

export function setup(form: HTMLFormElement): void {
    form.noValidate = true;
    form.addEventListener("submit", (event) => {
        clearInvalid(form);
        const invalid = findInvalid(form);
        if (!invalid) {
            return;
        }
        event.preventDefault();
        invalid.setAttribute("aria-invalid", "true");
        const focusTarget =
            invalid.matches("input, textarea")
                ? invalid
                : invalid.querySelector<HTMLElement>('input[type="radio"]');
        focusTarget?.focus();
    });
}

export function init(): void {
    document.querySelectorAll<HTMLFormElement>("[data-review-form]").forEach(setup);
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
} else {
    init();
}
