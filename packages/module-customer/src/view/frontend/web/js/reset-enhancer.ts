import { enhanceValidation, required, minLength, matches } from "MageObsidian_Customer::js/form-validation";

// Reset-password page entry (reached from the emailed link). Inline length/match
// validation only; the native POST to resetPasswordPost sets the new password.

function init(): void {
    const form = document.querySelector<HTMLFormElement>("[data-reset-form]");
    if (!form) {
        return;
    }

    const requiredMsg = form.dataset.errRequired;
    const minChars = Number(form.dataset.passwordMinLength || "8");

    enhanceValidation(form, {
        password: [required(requiredMsg), minLength(minChars, form.dataset.errMinlength)],
        password_confirmation: [required(requiredMsg), matches("password", form.dataset.errMatch)],
    });
}

init();
