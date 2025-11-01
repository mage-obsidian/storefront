import { describe, it, expect, vi, beforeEach } from "vitest";
import {
    required,
    email,
    minLength,
    matches,
    when,
    runRules,
    isValidEmail,
    enhanceValidation,
} from "./form-validation.ts";

describe("form-validation pure rules", () => {
    it("required flags empty/whitespace values", () => {
        expect(required()("", {})).toBe("This is a required field.");
        expect(required()("   ", {})).toBe("This is a required field.");
        expect(required()("x", {})).toBeNull();
        expect(required("Custom")("", {})).toBe("Custom");
    });

    it("email validates shape only on non-empty values", () => {
        expect(email()("", {})).toBeNull();
        expect(email()("not-an-email", {})).toBe("Please enter a valid email address.");
        expect(email()("a@b.co", {})).toBeNull();
        expect(isValidEmail("a@b.co")).toBe(true);
        expect(isValidEmail("a@b")).toBe(false);
    });

    it("minLength only triggers above empty and below the threshold", () => {
        expect(minLength(8)("", {})).toBeNull();
        expect(minLength(8)("short", {})).toContain("at least 8");
        expect(minLength(8)("longenough", {})).toBeNull();
    });

    it("matches compares against another field's value", () => {
        expect(matches("password")("abc", { password: "abc" })).toBeNull();
        expect(matches("password")("abc", { password: "xyz" })).toBe("The two passwords do not match.");
    });

    it("runRules returns the first failing message", () => {
        expect(runRules("", {}, [required(), email()])).toBe("This is a required field.");
        expect(runRules("bad", {}, [required(), email()])).toBe("Please enter a valid email address.");
        expect(runRules("a@b.co", {}, [required(), email()])).toBeNull();
    });

    it("when applies a rule only while the predicate holds", () => {
        const rule = when((all) => all.toggle === "1", required("req"));
        expect(rule("", { toggle: "1" })).toBe("req");
        expect(rule("", { toggle: "" })).toBeNull();
    });
});

describe("enhanceValidation DOM wiring", () => {
    function buildForm(): HTMLFormElement {
        document.body.innerHTML = `
            <form>
                <input id="f-email" name="email" />
                <input id="f-password" name="password" type="password" />
                <button type="submit">Go</button>
            </form>`;
        return document.querySelector("form") as HTMLFormElement;
    }

    beforeEach(() => {
        document.body.innerHTML = "";
    });

    it("disables native validation so the accessible handler owns submit", () => {
        const form = buildForm();
        enhanceValidation(form, { email: [required()] });
        // With JS active, native bubbles must not pre-empt our submit handler.
        expect(form.noValidate).toBe(true);
    });

    it("blocks an invalid submit, marks fields and inserts accessible errors", () => {
        const form = buildForm();
        const onValidSubmit = vi.fn();
        enhanceValidation(form, {
            email: [required(), email()],
            password: [required()],
        }, { onValidSubmit });

        const submit = new Event("submit", { cancelable: true });
        form.dispatchEvent(submit);

        expect(submit.defaultPrevented).toBe(true);
        expect(onValidSubmit).not.toHaveBeenCalled();

        const emailField = document.getElementById("f-email") as HTMLInputElement;
        expect(emailField.getAttribute("aria-invalid")).toBe("true");
        const errorNode = document.getElementById("f-email-error");
        expect(errorNode?.getAttribute("role")).toBe("alert");
        expect(emailField.getAttribute("aria-describedby")).toBe("f-email-error");
        // First invalid field receives focus.
        expect(document.activeElement).toBe(emailField);
    });

    it("hands a valid submit to onValidSubmit and clears errors", () => {
        const form = buildForm();
        const onValidSubmit = vi.fn();
        enhanceValidation(form, {
            email: [required(), email()],
            password: [required()],
        }, { onValidSubmit });

        (document.getElementById("f-email") as HTMLInputElement).value = "ada@shop.test";
        (document.getElementById("f-password") as HTMLInputElement).value = "secret";

        const submit = new Event("submit", { cancelable: true });
        form.dispatchEvent(submit);

        expect(submit.defaultPrevented).toBe(true); // intercepted for the callback
        expect(onValidSubmit).toHaveBeenCalledWith(
            { email: "ada@shop.test", password: "secret" },
            form,
        );
    });

    it("gates a conditional field on its checkbox", () => {
        document.body.innerHTML = `
            <form>
                <input type="checkbox" name="toggle" value="1" />
                <input id="f-extra" name="extra" />
                <button type="submit">Go</button>
            </form>`;
        const form = document.querySelector("form") as HTMLFormElement;
        enhanceValidation(form, {
            toggle: [],
            extra: [when((all) => all.toggle !== "", required())],
        });

        // Checkbox off → the empty conditional field does not block submit.
        let submit = new Event("submit", { cancelable: true });
        form.dispatchEvent(submit);
        expect(submit.defaultPrevented).toBe(false);

        // Checkbox on + field empty → blocked.
        (form.querySelector('[name="toggle"]') as HTMLInputElement).checked = true;
        submit = new Event("submit", { cancelable: true });
        form.dispatchEvent(submit);
        expect(submit.defaultPrevented).toBe(true);
    });

    it("validates and flags a required select", () => {
        document.body.innerHTML = `
            <form>
                <select id="f-region" name="region_id">
                    <option value="">Pick one</option>
                    <option value="1">Alabama</option>
                </select>
                <button type="submit">Go</button>
            </form>`;
        const form = document.querySelector("form") as HTMLFormElement;
        enhanceValidation(form, { region_id: [required()] });

        let submit = new Event("submit", { cancelable: true });
        form.dispatchEvent(submit);
        expect(submit.defaultPrevented).toBe(true);
        expect(document.getElementById("f-region")?.getAttribute("aria-invalid")).toBe("true");

        (document.getElementById("f-region") as HTMLSelectElement).value = "1";
        submit = new Event("submit", { cancelable: true });
        form.dispatchEvent(submit);
        expect(submit.defaultPrevented).toBe(false);
    });

    it("lets a valid form submit natively when no callback is given", () => {
        const form = buildForm();
        enhanceValidation(form, { email: [required(), email()] });

        (document.getElementById("f-email") as HTMLInputElement).value = "ada@shop.test";

        const submit = new Event("submit", { cancelable: true });
        form.dispatchEvent(submit);

        // Not prevented → the native POST proceeds.
        expect(submit.defaultPrevented).toBe(false);
    });
});
