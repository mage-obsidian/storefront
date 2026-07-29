import { describe, it, expect, beforeEach } from "vitest";
import { setButtonBusy } from "./button-state.ts";

function button(label = "Add to cart"): HTMLButtonElement {
    document.body.innerHTML = `<button type="submit">${label}</button>`;
    return document.querySelector("button") as HTMLButtonElement;
}

beforeEach(() => {
    document.body.innerHTML = "";
});

describe("setButtonBusy", () => {
    it("swaps the label for a spinner and locks the button", () => {
        const element = button();

        setButtonBusy(element, true);

        expect(element.querySelector(".obsidian-button__spinner")).not.toBeNull();
        expect(element.disabled).toBe(true);
        expect(element.getAttribute("aria-busy")).toBe("true");
    });

    it("keeps the label in the box so the button cannot change size", () => {
        const element = button();

        setButtonBusy(element, true);

        const label = element.querySelector(".obsidian-button__label");
        expect(label?.textContent?.trim()).toBe("Add to cart");
        expect(element.classList.contains("is-loading")).toBe(true);
    });

    it("restores the exact markup it replaced", () => {
        const element = button('Add <b>2</b> to cart');
        const before = element.innerHTML;

        setButtonBusy(element, true);
        setButtonBusy(element, false);

        expect(element.innerHTML).toBe(before);
        expect(element.disabled).toBe(false);
        expect(element.hasAttribute("aria-busy")).toBe(false);
        expect(element.classList.contains("is-loading")).toBe(false);
    });

    it("ignores a second busy call, so the label is never lost", () => {
        const element = button();

        setButtonBusy(element, true);
        setButtonBusy(element, true);
        setButtonBusy(element, false);

        expect(element.innerHTML).toBe("Add to cart");
    });

    it("is a no-op when releasing a button that was never busy, or with none at all", () => {
        const element = button();

        setButtonBusy(element, false);
        expect(element.textContent).toBe("Add to cart");
        expect(() => setButtonBusy(null, true)).not.toThrow();
    });
});
