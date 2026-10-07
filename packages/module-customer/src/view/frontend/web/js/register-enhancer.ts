// This file is part of the MageObsidian - Customer project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { requireCookies } from "mage-obsidian/runtime/cookies.ts";
import { requiredProfileRules } from "MageObsidian_Customer::js/profile-fields";
import { bindPasswordPolicy, passwordClasses } from "MageObsidian_Customer::js/password-policy";
import { enhanceValidation, required, email, minLength, matches } from "MageObsidian_Storefront::js/form-validation";

// Registration page entry. Inline validation only — account creation runs the
// native createPost flow (email confirmation, redirects) untouched. No Pinia /
// customer-data here, so the register page stays light.

function init(): void {
    const form = document.querySelector<HTMLFormElement>("[data-register-form]");
    if (!form) {
        return;
    }

    form.addEventListener("submit", (event) => {
        if (!requireCookies()) {
            event.preventDefault();
        }
    });

    const requiredMsg = form.dataset.errRequired;
    const policy = bindPasswordPolicy(form, "password");
    const minChars = policy.minLength;

    enhanceValidation(form, {
        firstname: [required(requiredMsg)],
        lastname: [required(requiredMsg)],
        email: [required(requiredMsg), email(form.dataset.errEmail)],
        password: [
            required(requiredMsg),
            minLength(minChars, form.dataset.errMinlength),
            passwordClasses(policy.classes, form.dataset.errClasses ?? ""),
        ],
        password_confirmation: [required(requiredMsg), matches("password", form.dataset.errMatch)],
        ...requiredProfileRules(form, requiredMsg),
    });
}

init();
