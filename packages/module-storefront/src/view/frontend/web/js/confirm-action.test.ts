import { afterEach, beforeEach, describe, expect, it } from "vitest";
// The import is what binds the document, so the delegated tests below need no setup.
import { bindConfirmForms, confirmAction } from "./confirm-action.ts";

const flush = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));

const dialogOf = (): HTMLDialogElement => {
    const dialogs = document.querySelectorAll<HTMLDialogElement>(".confirm-dialog");
    if (dialogs.length !== 1) {
        throw new Error(`Expected exactly one confirmation dialog, found ${dialogs.length}.`);
    }

    return dialogs[0];
};

const buttonsOf = (dialog: HTMLDialogElement): HTMLButtonElement[] =>
    Array.from(dialog.querySelectorAll("button"));

const formWithConfirm = (): HTMLFormElement => {
    document.body.innerHTML = `
        <form action="/delete" method="post"
              data-confirm-title="Delete this address?"
              data-confirm-body="This cannot be undone."
              data-confirm-ok="Delete"
              data-confirm-cancel="Keep it">
            <button type="submit" name="op" value="delete">Delete</button>
        </form>`;

    return document.querySelector("form") as HTMLFormElement;
};

// Watches on the document *after* the module bound it, so each entry is the
// enhancer's own verdict on that submit — read before this listener stops the
// navigation happy-dom would otherwise attempt.
const watchSubmits = (): { held: boolean[]; stop: () => void } => {
    const held: boolean[] = [];
    const listener = (event: Event): void => {
        held.push(event.defaultPrevented);
        event.preventDefault();
    };
    document.addEventListener("submit", listener);

    return { held, stop: () => document.removeEventListener("submit", listener) };
};

describe("confirmAction", () => {
    beforeEach(() => {
        document.body.innerHTML = "";
    });

    it("resolves true when the confirming button is pressed", async () => {
        const answer = confirmAction({
            title: "Delete this address?",
            confirmLabel: "Delete",
            cancelLabel: "Cancel",
        });

        buttonsOf(dialogOf())[1].click();

        await expect(answer).resolves.toBe(true);
        expect(document.querySelector(".confirm-dialog")).toBeNull();
    });

    it("resolves false when cancelled, and on a bare close (Escape)", async () => {
        const cancelled = confirmAction({ title: "T", confirmLabel: "Yes", cancelLabel: "No" });
        buttonsOf(dialogOf())[0].click();
        await expect(cancelled).resolves.toBe(false);

        const escaped = confirmAction({ title: "T", confirmLabel: "Yes", cancelLabel: "No" });
        dialogOf().close();
        await expect(escaped).resolves.toBe(false);
    });

    it("renders the copy as text, cancel first", async () => {
        const answer = confirmAction({
            title: "Delete this address?",
            body: "This cannot be undone.",
            confirmLabel: "Delete",
            cancelLabel: "Keep it",
        });

        const dialog = dialogOf();
        expect(dialog.querySelector(".confirm-dialog__title")?.textContent).toBe(
            "Delete this address?",
        );
        expect(dialog.querySelector(".confirm-dialog__body")?.textContent).toBe(
            "This cannot be undone.",
        );
        expect(buttonsOf(dialog).map((button) => button.textContent)).toEqual([
            "Keep it",
            "Delete",
        ]);

        buttonsOf(dialog)[0].click();
        await answer;
    });

    it("omits the body paragraph when there is no body", async () => {
        const answer = confirmAction({ title: "T", confirmLabel: "Yes", cancelLabel: "No" });

        expect(dialogOf().querySelector(".confirm-dialog__body")).toBeNull();

        dialogOf().close();
        await answer;
    });
});

describe("bindConfirmForms", () => {
    let watcher: ReturnType<typeof watchSubmits>;

    beforeEach(() => {
        document.body.innerHTML = "";
        watcher = watchSubmits();
    });

    afterEach(() => watcher.stop());

    it("holds the submit back until the dialog is answered, then lets it through once", async () => {
        const form = formWithConfirm();

        form.requestSubmit(form.querySelector("button"));
        await flush();

        expect(watcher.held).toEqual([true]);

        buttonsOf(dialogOf())[1].click();
        await flush();

        expect(watcher.held).toEqual([true, false]);
        expect(form.hasAttribute("data-confirmed")).toBe(true);
    });

    it("does not submit when the answer is no", async () => {
        const form = formWithConfirm();

        form.requestSubmit(form.querySelector("button"));
        await flush();
        buttonsOf(dialogOf())[0].click();
        await flush();

        expect(watcher.held).toEqual([true]);
        expect(form.hasAttribute("data-confirmed")).toBe(false);
    });

    it("leaves forms without a confirm title alone", async () => {
        document.body.innerHTML = `<form action="/save" method="post"><button type="submit">Save</button></form>`;
        const form = document.querySelector("form") as HTMLFormElement;

        form.requestSubmit(form.querySelector("button"));
        await flush();

        expect(watcher.held).toEqual([false]);
        expect(document.querySelector(".confirm-dialog")).toBeNull();
    });

    it("binds a root only once, so a second call cannot ask twice", async () => {
        bindConfirmForms(document);
        const form = formWithConfirm();

        form.requestSubmit(form.querySelector("button"));
        await flush();

        expect(dialogOf()).not.toBeNull();

        buttonsOf(dialogOf())[0].click();
        await flush();
    });
});
