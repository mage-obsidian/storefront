import { beforeEach, describe, expect, it } from "vitest";
import { bindPasswordToggles } from "./password-toggle.ts";

const render = (markup: string): HTMLInputElement => {
    document.body.innerHTML = markup;
    bindPasswordToggles();
    return document.querySelector("input") as HTMLInputElement;
};

const passwordField = (formAttrs = ""): HTMLInputElement =>
    render(`
        <form ${formAttrs}>
            <div class="field">
                <label class="field__label" for="pwd">Password</label>
                <input class="field__control" type="password" id="pwd" name="password">
            </div>
        </form>`);

const toggle = (): HTMLButtonElement =>
    document.querySelector(".field-password__toggle") as HTMLButtonElement;

beforeEach(() => {
    document.body.innerHTML = "";
});

describe("bindPasswordToggles", () => {
    it("gives every password field a way to read what was typed", () => {
        const input = passwordField();

        expect(input.parentElement?.className).toBe("field-password");
        expect(toggle()).not.toBeNull();
        expect(toggle().type).toBe("button");
    });

    it("reveals and hides the value", () => {
        const input = passwordField();

        toggle().click();
        expect(input.type).toBe("text");

        toggle().click();
        expect(input.type).toBe("password");
    });

    it("reports its state to assistive tech", () => {
        const input = passwordField();

        expect(toggle().getAttribute("aria-pressed")).toBe("false");
        expect(toggle().getAttribute("aria-label")).toBe("Show password");
        expect(toggle().getAttribute("aria-controls")).toBe(input.id);

        toggle().click();

        expect(toggle().getAttribute("aria-pressed")).toBe("true");
        expect(toggle().getAttribute("aria-label")).toBe("Hide password");
    });

    it("takes its wording from the form when the page supplies one", () => {
        passwordField('data-pwd-show="Mostrar" data-pwd-hide="Ocultar"');

        expect(toggle().getAttribute("aria-label")).toBe("Mostrar");
        toggle().click();
        expect(toggle().getAttribute("aria-label")).toBe("Ocultar");
    });

    it("swaps the icon so the control does not read as stuck", () => {
        passwordField();
        const href = () => toggle().querySelector("use")?.getAttribute("href") ?? "";

        expect(href()).toContain("eye.svg");
        toggle().click();
        expect(href()).toContain("eye-slash.svg");
    });

    it("keeps the caret in the field after toggling", () => {
        const input = passwordField();

        toggle().click();

        expect(document.activeElement).toBe(input);
    });

    it("leaves other inputs alone", () => {
        render(`
            <div class="field">
                <input class="field__control" type="email" id="email" name="email">
            </div>`);

        expect(toggle()).toBeNull();
    });

    it("does not stack a second toggle when it runs again", () => {
        passwordField();
        bindPasswordToggles();

        expect(document.querySelectorAll(".field-password__toggle")).toHaveLength(1);
    });
});
