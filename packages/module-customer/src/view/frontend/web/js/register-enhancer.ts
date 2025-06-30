import { enhanceValidation, required, email, minLength, matches } from "MageObsidian_Customer::js/form-validation";

// Registration page entry. Inline validation only — account creation runs the
// native createPost flow (email confirmation, redirects) untouched. No Pinia /
// customer-data here, so the register page stays light.

function init(): void {
    const form = document.querySelector<HTMLFormElement>("[data-register-form]");
    if (!form) {
        return;
    }

    const requiredMsg = form.dataset.errRequired;
    const minChars = Number(form.dataset.passwordMinLength || "8");

    enhanceValidation(form, {
        firstname: [required(requiredMsg)],
        lastname: [required(requiredMsg)],
        email: [required(requiredMsg), email(form.dataset.errEmail)],
        password: [required(requiredMsg), minLength(minChars, form.dataset.errMinlength)],
        password_confirmation: [required(requiredMsg), matches("password", form.dataset.errMatch)],
    });
}

init();
