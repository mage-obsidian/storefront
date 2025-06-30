import { enhanceValidation, required, email } from "MageObsidian_Customer::js/form-validation";

// Forgot-password page entry. Inline email validation only; the native POST to
// forgotPasswordPost sends the reset email.

function init(): void {
    const form = document.querySelector<HTMLFormElement>("[data-forgot-form]");
    if (!form) {
        return;
    }

    enhanceValidation(form, {
        email: [required(form.dataset.errRequired), email(form.dataset.errEmail)],
    });
}

init();
