// The rating groups are validated here and not by the shared engine: a radio
// group has no `.field__error` node of its own, and `required` on sr-only radios
// cannot host the browser's validation bubble.

import { enhanceValidation, required } from "MageObsidian_Storefront::js/form-validation";

const RATING_GROUP = '[role="radiogroup"]';
const FORM_SELECTOR = "[data-review-form]";
const REQUIRED_MESSAGE_ATTR = "data-err-required";

export function findUnratedGroup(form: HTMLFormElement): HTMLElement | null {
    for (const group of form.querySelectorAll<HTMLElement>(RATING_GROUP)) {
        const radios = group.querySelectorAll<HTMLInputElement>('input[type="radio"]');
        if (!Array.from(radios).some((radio) => radio.checked)) {
            return group;
        }
    }
    return null;
}

export function setup(form: HTMLFormElement): void {
    const message = form.getAttribute(REQUIRED_MESSAGE_ATTR);
    const isRequired = message === null ? required() : required(message);

    enhanceValidation(
        form,
        {
            nickname: [isRequired],
            title: [isRequired],
            detail: [isRequired],
        },
        {
            onValidSubmit: () => {
                const unrated = findUnratedGroup(form);
                if (!unrated) {
                    // Bypasses the listeners, which is what lets the native POST through.
                    form.submit();
                    return;
                }
                unrated.setAttribute("aria-invalid", "true");
                unrated.querySelector<HTMLInputElement>('input[type="radio"]')?.focus();
            },
        },
    );

    form.addEventListener("change", (event) => {
        if (event.target instanceof HTMLInputElement && event.target.type === "radio") {
            event.target.closest(RATING_GROUP)?.removeAttribute("aria-invalid");
        }
    });
}

export function init(): void {
    document.querySelectorAll<HTMLFormElement>(FORM_SELECTOR).forEach(setup);
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
} else {
    init();
}
