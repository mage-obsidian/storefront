// This file is part of the MageObsidian - Customer project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect } from "vitest";
import { requiredProfileRules } from "./profile-fields.ts";

function formWith(markup: string): HTMLFormElement {
    document.body.innerHTML = `<form>${markup}</form>`;
    return document.querySelector("form") as HTMLFormElement;
}

describe("requiredProfileRules", () => {
    it("builds a rule for each profile field the store marks required", () => {
        const form = formWith(`
            <input name="taxvat" required />
            <select name="prefix" required><option value="">-</option></select>
            <input name="dob" />
        `);

        expect(Object.keys(requiredProfileRules(form))).toEqual(["prefix", "taxvat"]);
    });

    it("builds nothing for a profile field that is absent or optional", () => {
        const form = formWith('<input name="firstname" required /><input name="suffix" />');

        expect(requiredProfileRules(form)).toEqual({});
    });

    it("blocks a blank value and names the message it was given", () => {
        const form = formWith('<input name="taxvat" required />');
        const [rule] = requiredProfileRules(form, "Fill this in")["taxvat"];

        expect(rule("", {})).toBe("Fill this in");
        expect(rule("123", {})).toBeNull();
    });
});
