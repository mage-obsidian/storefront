// This file is part of the MageObsidian - Customer project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { enhanceValidation, required, email } from "MageObsidian_Storefront::js/form-validation";

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
