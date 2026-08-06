/**
 * Confirmation before a destructive native POST (delete an address, remove a
 * wish list item, drop a stored card). A native <dialog> rather than a component:
 * the top layer, the backdrop, Escape and the focus trap come from the browser.
 *
 * Enhancement only. Without JS the form posts straight away, which is the exact
 * behaviour every one of these pages had before.
 */

export interface ConfirmOptions {
    title: string;
    body?: string;
    confirmLabel: string;
    cancelLabel: string;
}

const CONFIRMED = "data-confirmed";

const bound = new WeakSet<EventTarget>();

export function confirmAction(options: ConfirmOptions, doc: Document = document): Promise<boolean> {
    const dialog = doc.createElement("dialog");
    dialog.className = "confirm-dialog";

    const title = doc.createElement("h2");
    title.className = "confirm-dialog__title";
    title.textContent = options.title;
    dialog.append(title);

    if (options.body) {
        const body = doc.createElement("p");
        body.className = "confirm-dialog__body";
        body.textContent = options.body;
        dialog.append(body);
    }

    const actions = doc.createElement("div");
    actions.className = "confirm-dialog__actions";

    const button = (label: string, variant: string, answer: string): HTMLButtonElement => {
        const element = doc.createElement("button");
        element.type = "button";
        element.className = `btn btn--${variant} btn--sm`;
        element.textContent = label;
        element.addEventListener("click", () => dialog.close(answer));

        return element;
    };

    const cancel = button(options.cancelLabel, "outline", "cancel");
    actions.append(cancel, button(options.confirmLabel, "solid", "confirm"));
    dialog.append(actions);
    doc.body.append(dialog);

    return new Promise<boolean>((resolve) => {
        dialog.addEventListener(
            "close",
            () => {
                const accepted = dialog.returnValue === "confirm";
                dialog.remove();
                resolve(accepted);
            },
            { once: true },
        );
        dialog.showModal();
        cancel.focus();
    });
}

function optionsOf(form: HTMLFormElement): ConfirmOptions | null {
    const title = form.dataset.confirmTitle;
    if (!title) {
        return null;
    }

    return {
        title,
        body: form.dataset.confirmBody,
        confirmLabel: form.dataset.confirmOk ?? "OK",
        cancelLabel: form.dataset.confirmCancel ?? "Cancel",
    };
}

/**
 * Delegated: any form under `root` carrying `data-confirm-title` asks first. The
 * re-submit goes through requestSubmit with the original submitter, so native
 * validation and the submitter's name/value survive the detour; the marker on the
 * form is what stops the listener from asking a second time.
 *
 * Binding the same root twice is a no-op: the module binds the document on import,
 * and several pages of an area may each ask for the enhancer.
 */
export function bindConfirmForms(root: EventTarget = document, doc: Document = document): void {
    if (bound.has(root)) {
        return;
    }
    bound.add(root);

    root.addEventListener("submit", (event) => {
        const form = event.target as HTMLFormElement | null;
        if (!(form instanceof HTMLFormElement) || form.hasAttribute(CONFIRMED)) {
            return;
        }

        const options = optionsOf(form);
        if (!options) {
            return;
        }

        event.preventDefault();
        const submitter = (event as SubmitEvent).submitter as HTMLElement | null;

        void confirmAction(options, doc).then((accepted) => {
            if (!accepted) {
                return;
            }
            form.setAttribute(CONFIRMED, "");
            form.requestSubmit(submitter instanceof HTMLButtonElement ? submitter : null);
        });
    });
}

bindConfirmForms();
