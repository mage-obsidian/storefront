// Accessible inline validation for the auth forms — the progressive layer over
// the native HTML5-validated <form>. The validators are pure (unit-tested in
// isolation); enhanceValidation only wires them to the DOM: it toggles
// aria-invalid, links each field to a role=alert error node via aria-describedby,
// focuses the first invalid field on submit, and either hands a valid submit to a
// callback (AJAX login) or lets it proceed natively (register / forgot / reset).

export type Rule = (value: string, all: Record<string, string>) => string | null;

export function isValidEmail(value: string): boolean {
    // Pragmatic shape check (server-side validation remains authoritative).
    return /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(value.trim());
}

export const required = (message = "This is a required field."): Rule =>
    (value) => (value.trim() === "" ? message : null);

export const email = (message = "Please enter a valid email address."): Rule =>
    (value) => (value.trim() !== "" && !isValidEmail(value) ? message : null);

export const minLength = (length: number, message?: string): Rule =>
    (value) => (value !== "" && value.length < length
        ? (message ?? `Please enter at least ${length} characters.`)
        : null);

export const matches = (otherField: string, message = "The two passwords do not match."): Rule =>
    (value, all) => (value !== (all[otherField] ?? "") ? message : null);

/** First failing rule's message, or null when the value passes every rule. */
export function runRules(value: string, all: Record<string, string>, rules: Rule[]): string | null {
    for (const rule of rules) {
        const error = rule(value, all);
        if (error !== null) {
            return error;
        }
    }
    return null;
}

export interface FieldRules {
    [fieldName: string]: Rule[];
}

export interface EnhanceOptions {
    /**
     * Called with the collected values when every field passes. When provided,
     * the native submit is suppressed (the caller drives an AJAX flow); when
     * omitted, a valid form submits natively.
     */
    onValidSubmit?: (values: Record<string, string>, form: HTMLFormElement) => void;
}

function collectValues(form: HTMLFormElement, fields: string[]): Record<string, string> {
    const values: Record<string, string> = {};
    for (const name of fields) {
        const field = form.elements.namedItem(name);
        values[name] = field instanceof HTMLInputElement ? field.value : "";
    }
    return values;
}

function setFieldError(form: HTMLFormElement, name: string, message: string | null): void {
    const field = form.elements.namedItem(name);
    if (!(field instanceof HTMLInputElement)) {
        return;
    }
    const errorId = `${field.id || name}-error`;
    let node = document.getElementById(errorId);

    if (message === null) {
        field.removeAttribute("aria-invalid");
        node?.remove();
        return;
    }

    field.setAttribute("aria-invalid", "true");
    if (!node) {
        node = document.createElement("p");
        node.id = errorId;
        node.className = "field-error";
        node.setAttribute("role", "alert");
        field.setAttribute("aria-describedby", errorId);
        field.insertAdjacentElement("afterend", node);
    }
    node.textContent = message;
}

/**
 * Wire accessible inline validation onto a form. Fields are validated on blur and
 * on submit; `data-error-<rule>` attributes (set in Twig with translated copy)
 * override the default English messages where present.
 */
export function enhanceValidation(
    form: HTMLFormElement,
    fieldRules: FieldRules,
    options: EnhanceOptions = {},
): void {
    const fields = Object.keys(fieldRules);

    // With JS active we own validation, so suppress the browser's native bubble
    // (it would otherwise block the submit event before our accessible handler
    // runs). The required/type/minlength attributes still guard the no-JS path.
    form.noValidate = true;

    const validateField = (name: string): boolean => {
        const all = collectValues(form, fields);
        const error = runRules(all[name] ?? "", all, fieldRules[name]);
        setFieldError(form, name, error);
        return error === null;
    };

    for (const name of fields) {
        const field = form.elements.namedItem(name);
        if (field instanceof HTMLInputElement) {
            // Validate on blur once the user has engaged; clears as they fix it.
            field.addEventListener("blur", () => validateField(name));
        }
    }

    form.addEventListener("submit", (event) => {
        let firstInvalid: string | null = null;
        for (const name of fields) {
            if (!validateField(name) && firstInvalid === null) {
                firstInvalid = name;
            }
        }

        if (firstInvalid !== null) {
            event.preventDefault();
            const field = form.elements.namedItem(firstInvalid);
            if (field instanceof HTMLInputElement) {
                field.focus();
            }
            return;
        }

        if (options.onValidSubmit) {
            event.preventDefault();
            options.onValidSubmit(collectValues(form, fields), form);
        }
    });
}
