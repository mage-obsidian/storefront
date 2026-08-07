import { beforeEach, describe, expect, it } from "vitest";
import { bindBusyForms, releaseBusyButtons } from "./form-busy.ts";

const flush = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));

const render = (markup: string): HTMLFormElement => {
    document.body.innerHTML = markup;
    return document.querySelector("form") as HTMLFormElement;
};

const optedIn = (): HTMLFormElement =>
    render(`
        <form action="/save" method="post" data-busy-submit>
            <button type="submit" class="btn btn--solid">Save Address</button>
        </form>`);

beforeEach(() => {
    document.body.innerHTML = "";
});

describe("bindBusyForms", () => {
    it("locks the submit button and swaps its label for a spinner", async () => {
        const form = optedIn();
        const button = form.querySelector("button") as HTMLButtonElement;

        form.requestSubmit(button);
        await flush();

        expect(button.disabled).toBe(true);
        expect(button.getAttribute("aria-busy")).toBe("true");
        expect(button.querySelector(".btn__spinner")).not.toBeNull();
        expect(button.querySelector(".btn__label")?.textContent).toBe("Save Address");
    });

    it("leaves a form that did not opt in alone", async () => {
        const form = render(`
            <form action="/search" method="get">
                <button type="submit">Search</button>
            </form>`);
        const button = form.querySelector("button") as HTMLButtonElement;

        form.requestSubmit(button);
        await flush();

        expect(button.disabled).toBe(false);
    });

    it("stays out of the way when another listener cancels the submit", async () => {
        const form = optedIn();
        const button = form.querySelector("button") as HTMLButtonElement;
        form.addEventListener("submit", (event) => event.preventDefault());

        form.requestSubmit(button);
        await flush();

        expect(button.disabled).toBe(false);
        expect(button.querySelector(".btn__spinner")).toBeNull();
    });

    it("busies the button that submitted, not the first one in the form", async () => {
        const form = render(`
            <form action="/orders" method="post" data-busy-submit>
                <button type="submit" name="op" value="cancel">Cancel order</button>
                <button type="submit" name="op" value="reorder">Reorder</button>
            </form>`);
        const [cancel, reorder] = Array.from(form.querySelectorAll("button"));

        form.requestSubmit(reorder);
        await flush();

        expect(reorder.disabled).toBe(true);
        expect(cancel.disabled).toBe(false);
    });

    it("falls back to the form's submit button when there is no submitter", async () => {
        const form = optedIn();
        const button = form.querySelector("button") as HTMLButtonElement;

        form.requestSubmit();
        await flush();

        expect(button.disabled).toBe(true);
    });

    it("releases every busy button on pageshow", async () => {
        const form = optedIn();
        const button = form.querySelector("button") as HTMLButtonElement;

        form.requestSubmit(button);
        await flush();
        expect(button.disabled).toBe(true);

        window.dispatchEvent(new Event("pageshow"));

        expect(button.disabled).toBe(false);
        expect(button.textContent).toBe("Save Address");
    });

    it("restores the original markup when released directly", async () => {
        const form = optedIn();
        const button = form.querySelector("button") as HTMLButtonElement;

        form.requestSubmit(button);
        await flush();
        releaseBusyButtons();

        expect(button.innerHTML.trim()).toBe("Save Address");
        expect(button.hasAttribute("aria-busy")).toBe(false);
    });

    it("binds a root once", async () => {
        const form = optedIn();
        const button = form.querySelector("button") as HTMLButtonElement;
        bindBusyForms();
        bindBusyForms();

        form.requestSubmit(button);
        await flush();

        expect(button.querySelectorAll(".btn__spinner")).toHaveLength(1);
    });
});
