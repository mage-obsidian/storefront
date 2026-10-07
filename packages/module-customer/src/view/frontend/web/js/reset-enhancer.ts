// This file is part of the MageObsidian - Customer project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { bindPasswordPolicy, passwordClasses } from "MageObsidian_Customer::js/password-policy";
import { enhanceValidation, required, minLength, matches } from "MageObsidian_Storefront::js/form-validation";

// Reset-password page entry (reached from the emailed link). Inline length/match
// validation only; the native POST to resetPasswordPost sets the new password.

function init(): void {
    const form = document.querySelector<HTMLFormElement>("[data-reset-form]");
    if (!form) {
        return;
    }

    const requiredMsg = form.dataset.errRequired;
    const policy = bindPasswordPolicy(form, "password");
    const minChars = policy.minLength;

    enhanceValidation(form, {
        password: [
            required(requiredMsg),
            minLength(minChars, form.dataset.errMinlength),
            passwordClasses(policy.classes, form.dataset.errClasses ?? ""),
        ],
        password_confirmation: [required(requiredMsg), matches("password", form.dataset.errMatch)],
    });
}

init();
