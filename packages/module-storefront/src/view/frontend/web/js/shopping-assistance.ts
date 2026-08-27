/**
 * The opt-in a customer gives — or takes back — for an administrator to browse
 * inside their session.
 *
 * The platform reads a value, not a checkbox: an unchecked checkbox posts
 * nothing at all, so a customer who turned assistance off would post an empty
 * form field and Magento would read no decision from it. The hidden field
 * carries the decision either way, which is what makes "off" mean off.
 */
export const ALLOWED = "allowed";
export const DENIED = "denied";

const FIELD = "[data-assistance-decision]";
const CHECKBOX = "[data-assistance-checkbox]";

const bound = new WeakSet<EventTarget>();

export function decisionFor(checked: boolean): string {
    return checked ? ALLOWED : DENIED;
}

export function recordDecision(form: ParentNode): string | null {
    const checkbox = form.querySelector<HTMLInputElement>(CHECKBOX);
    const field = form.querySelector<HTMLInputElement>(FIELD);
    if (!checkbox || !field) {
        return null;
    }

    field.value = decisionFor(checkbox.checked);
    return field.value;
}

export function bindShoppingAssistance(root: ParentNode & EventTarget = document): void {
    if (bound.has(root)) {
        return;
    }
    bound.add(root);

    root.querySelectorAll<HTMLElement>(CHECKBOX).forEach((checkbox) => {
        const form = checkbox.closest("form");
        if (form) {
            recordDecision(form);
        }
    });

    root.addEventListener("change", (event) => {
        const target = event.target;
        if (target instanceof HTMLInputElement && target.matches(CHECKBOX)) {
            const form = target.closest("form");
            if (form) {
                recordDecision(form);
            }
        }
    });

    root.addEventListener(
        "submit",
        (event) => {
            const form = event.target;
            if (form instanceof HTMLFormElement) {
                recordDecision(form);
            }
        },
        true,
    );
}

bindShoppingAssistance();
