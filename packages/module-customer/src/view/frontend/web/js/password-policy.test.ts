// This file is part of the MageObsidian - Customer project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect } from "vitest";
import { bindPasswordPolicy, characterClasses, passwordClasses, strengthLevel } from "./password-policy.ts";

const POLICY = { minLength: 12, classes: 3 };

describe("characterClasses", () => {
    it("counts lower case, upper case, digits and special characters once each", () => {
        expect(characterClasses("")).toBe(0);
        expect(characterClasses("abc")).toBe(1);
        expect(characterClasses("abcDEF")).toBe(2);
        expect(characterClasses("abcDEF123")).toBe(3);
        expect(characterClasses("abcDEF123-_")).toBe(4);
    });
});

describe("passwordClasses", () => {
    it("refuses a value with fewer classes than the store requires", () => {
        const rule = passwordClasses(3, "Too few classes");

        expect(rule("aaaaaaaaaaaa", {})).toBe("Too few classes");
        expect(rule("aaaaaaAAAA11", {})).toBeNull();
    });

    it("leaves an empty value to the required rule", () => {
        expect(passwordClasses(3, "x")("", {})).toBeNull();
    });
});

describe("strengthLevel", () => {
    it("is zero for nothing typed", () => {
        expect(strengthLevel("", POLICY)).toBe(0);
    });

    it("is one while the policy is not met, however long the value is", () => {
        expect(strengthLevel("abc", POLICY)).toBe(1);
        expect(strengthLevel("aaaaaaaaaaaaaaaa", POLICY)).toBe(1);
        expect(strengthLevel("aA1", POLICY)).toBe(1);
    });

    it("is two when it meets the policy and nothing more", () => {
        expect(strengthLevel("aaaaaaAAAA11", POLICY)).toBe(2);
    });

    it("rises with extra length or an extra class", () => {
        expect(strengthLevel("aaaaaaaaaaAAAA11", POLICY)).toBe(3);
        expect(strengthLevel("aaaaaaAAA11-", POLICY)).toBe(3);
    });

    it("tops out at four with extra length and every class", () => {
        expect(strengthLevel("Fixture-Pass-1234", POLICY)).toBe(4);
    });
});

describe("bindPasswordPolicy", () => {
    function formWith(): HTMLFormElement {
        document.body.innerHTML = `
            <form data-password-min-length="12" data-password-classes="3">
                <input name="password" type="password" />
                <div data-password-strength data-level="0" data-label-1="Weak" data-label-2="Fair" data-label-3="Good" data-label-4="Strong">
                    <span data-strength-label></span>
                </div>
            </form>`;
        return document.querySelector("form") as HTMLFormElement;
    }

    it("updates the level and the label as the shopper types", () => {
        const form = formWith();
        bindPasswordPolicy(form, "password");
        const input = form.querySelector("input") as HTMLInputElement;
        const meter = form.querySelector("[data-password-strength]") as HTMLElement;

        input.value = "abc";
        input.dispatchEvent(new Event("input"));
        expect(meter.dataset.level).toBe("1");
        expect(meter.querySelector("[data-strength-label]")?.textContent).toBe("Weak");

        input.value = "Fixture-Pass-1234";
        input.dispatchEvent(new Event("input"));
        expect(meter.dataset.level).toBe("4");
        expect(meter.querySelector("[data-strength-label]")?.textContent).toBe("Strong");
    });

    it("empties the label when the field is cleared", () => {
        const form = formWith();
        bindPasswordPolicy(form, "password");
        const input = form.querySelector("input") as HTMLInputElement;

        input.value = "abc";
        input.dispatchEvent(new Event("input"));
        input.value = "";
        input.dispatchEvent(new Event("input"));

        expect(form.querySelector("[data-strength-label]")?.textContent).toBe("");
    });

    it("returns the policy it read from the form", () => {
        expect(bindPasswordPolicy(formWith(), "password")).toEqual({ minLength: 12, classes: 3 });
    });

    it("does nothing when the form has no meter", () => {
        document.body.innerHTML = '<form><input name="password" /></form>';

        expect(() => bindPasswordPolicy(document.querySelector("form") as HTMLFormElement, "password")).not.toThrow();
    });
});
