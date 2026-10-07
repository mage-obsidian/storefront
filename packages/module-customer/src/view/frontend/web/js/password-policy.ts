// This file is part of the MageObsidian - Customer project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import type { Rule } from "MageObsidian_Storefront::js/form-validation";

export interface PasswordPolicy {
    minLength: number;
    classes: number;
}

const EXTRA_LENGTH = 4;

export function characterClasses(value: string): number {
    return [/[a-z]/, /[A-Z]/, /\d/, /[^a-zA-Z\d]/].filter((pattern) => pattern.test(value)).length;
}

export const passwordClasses = (classes: number, message: string): Rule =>
    (value) => (value !== "" && characterClasses(value) < classes ? message : null);

export function strengthLevel(value: string, policy: PasswordPolicy): number {
    if (value === "") {
        return 0;
    }
    const classes = characterClasses(value);
    if (value.length < policy.minLength || classes < policy.classes) {
        return 1;
    }
    const extraLength = value.length >= policy.minLength + EXTRA_LENGTH ? 1 : 0;
    const extraClass = classes > policy.classes ? 1 : 0;

    return 2 + extraLength + extraClass;
}

export function readPasswordPolicy(form: HTMLFormElement): PasswordPolicy {
    return {
        minLength: Number(form.dataset.passwordMinLength || "8"),
        classes: Number(form.dataset.passwordClasses || "1"),
    };
}

export function bindPasswordPolicy(form: HTMLFormElement, fieldName: string): PasswordPolicy {
    const policy = readPasswordPolicy(form);
    const input = form.elements.namedItem(fieldName);
    const meter = form.querySelector<HTMLElement>("[data-password-strength]");
    if (!(input instanceof HTMLInputElement) || !meter) {
        return policy;
    }

    const label = meter.querySelector<HTMLElement>("[data-strength-label]");
    input.addEventListener("input", () => {
        const level = strengthLevel(input.value, policy);
        meter.dataset.level = String(level);
        if (label) {
            label.textContent = level === 0 ? "" : (meter.getAttribute(`data-label-${level}`) ?? "");
        }
    });

    return policy;
}
