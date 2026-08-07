import { setButtonBusy } from "MageObsidian_Storefront::js/button-state";

const FORM_ATTRIBUTE = "data-busy-submit";
const BUSY_ATTRIBUTE = "data-obsidian-busy";

const bound = new WeakSet<EventTarget>();

function submitButton(form: HTMLFormElement, submitter: EventTarget | null): HTMLButtonElement | null {
    if (submitter instanceof HTMLButtonElement) {
        return submitter;
    }

    return form.querySelector<HTMLButtonElement>('button[type="submit"], button:not([type])');
}

export function releaseBusyButtons(doc: Document = document): void {
    doc.querySelectorAll<HTMLButtonElement>(`[${BUSY_ATTRIBUTE}]`).forEach((button) => {
        setButtonBusy(button, false);
    });
}

export function bindBusyForms(root: EventTarget = document, doc: Document = document): void {
    if (bound.has(root)) {
        return;
    }
    bound.add(root);

    root.addEventListener("submit", (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute(FORM_ATTRIBUTE)) {
            return;
        }

        const button = submitButton(form, (event as SubmitEvent).submitter);
        setTimeout(() => {
            if (!event.defaultPrevented) {
                setButtonBusy(button, true);
            }
        }, 0);
    });

    doc.defaultView?.addEventListener("pageshow", () => releaseBusyButtons(doc));
}

bindBusyForms();
