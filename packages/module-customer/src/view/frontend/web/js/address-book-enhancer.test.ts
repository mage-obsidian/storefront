import { describe, it, expect, beforeEach, vi } from "vitest";
import { enhanceDeleteForms } from "./address-book-enhancer.ts";

describe("enhanceDeleteForms", () => {
    function buildBook(): HTMLElement {
        document.body.innerHTML = `
            <section data-address-book>
                <form data-delete-address>
                    <input type="hidden" name="id" value="7" />
                    <button type="submit" data-confirm="Delete this address?">Delete</button>
                </form>
            </section>`;
        return document.querySelector("[data-address-book]") as HTMLElement;
    }

    beforeEach(() => {
        document.body.innerHTML = "";
    });

    it("cancels the submit when the user declines the confirm", () => {
        const root = buildBook();
        enhanceDeleteForms(root, () => false);

        const submit = new Event("submit", { cancelable: true });
        (root.querySelector("form") as HTMLFormElement).dispatchEvent(submit);

        expect(submit.defaultPrevented).toBe(true);
    });

    it("lets the submit through when the user accepts", () => {
        const root = buildBook();
        const confirmFn = vi.fn(() => true);
        enhanceDeleteForms(root, confirmFn);

        const submit = new Event("submit", { cancelable: true });
        (root.querySelector("form") as HTMLFormElement).dispatchEvent(submit);

        expect(confirmFn).toHaveBeenCalledWith("Delete this address?");
        expect(submit.defaultPrevented).toBe(false);
    });
});
