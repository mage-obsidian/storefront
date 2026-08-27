import { beforeEach, describe, expect, it } from "vitest";
import { ALLOWED, bindShoppingAssistance, DENIED, decisionFor, recordDecision } from "./shopping-assistance.ts";

const render = (checked: boolean): HTMLFormElement => {
    document.body.innerHTML = `
        <form action="/customer/account/editPost" method="post">
            <label>
                <input type="checkbox" data-assistance-checkbox ${checked ? "checked" : ""}>
                <span>Allow remote shopping assistance</span>
            </label>
            <input type="hidden" name="assistance_allowed" value="" data-assistance-decision>
            <button type="submit">Save</button>
        </form>`;
    return document.querySelector("form") as HTMLFormElement;
};

const decision = (): string => (document.querySelector("[data-assistance-decision]") as HTMLInputElement).value;

beforeEach(() => {
    document.body.innerHTML = "";
});

describe("decisionFor", () => {
    it("says allowed and denied rather than on and missing", () => {
        expect(decisionFor(true)).toBe(ALLOWED);
        expect(decisionFor(false)).toBe(DENIED);
    });
});

describe("recordDecision", () => {
    /**
     * The regression this exists for: an unchecked checkbox posts nothing at
     * all, so a customer turning assistance off would post no decision and the
     * platform would keep the old one.
     */
    it("records a refusal, which an unchecked box would not have posted", () => {
        const form = render(false);

        expect(recordDecision(form)).toBe(DENIED);
        expect(decision()).toBe(DENIED);
    });

    it("records consent", () => {
        expect(recordDecision(render(true))).toBe(ALLOWED);
    });

    it("says nothing about a form that carries no opt-in", () => {
        document.body.innerHTML = "<form><button>Save</button></form>";

        expect(recordDecision(document.querySelector("form") as HTMLFormElement)).toBeNull();
    });
});

describe("bindShoppingAssistance", () => {
    it("seeds the decision before the customer touches anything", () => {
        render(true);

        bindShoppingAssistance(document.body);

        expect(decision()).toBe(ALLOWED);
    });

    it("follows the customer changing their mind", () => {
        const form = render(true);
        bindShoppingAssistance(document.body);

        const checkbox = form.querySelector("input[type=checkbox]") as HTMLInputElement;
        checkbox.checked = false;
        checkbox.dispatchEvent(new Event("change", { bubbles: true }));

        expect(decision()).toBe(DENIED);
    });

    it("records it again on submit, whatever else changed the box", () => {
        const form = render(false);
        bindShoppingAssistance(document.body);
        (form.querySelector("input[type=checkbox]") as HTMLInputElement).checked = true;

        form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));

        expect(decision()).toBe(ALLOWED);
    });
});
