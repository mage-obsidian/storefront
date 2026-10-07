// This file is part of the MageObsidian - Customer project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { required, type FieldRules } from "MageObsidian_Storefront::js/form-validation";

export const PROFILE_FIELDS = ["prefix", "middlename", "suffix", "dob", "gender", "taxvat"];

export function requiredProfileRules(form: HTMLFormElement, message?: string): FieldRules {
    const rules: FieldRules = {};
    for (const name of PROFILE_FIELDS) {
        const control = form.elements.namedItem(name);
        if (
            (control instanceof HTMLInputElement || control instanceof HTMLSelectElement) &&
            control.required
        ) {
            rules[name] = [required(message)];
        }
    }
    return rules;
}
