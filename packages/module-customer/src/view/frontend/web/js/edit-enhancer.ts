import { enhanceValidation, required, email, minLength, matches, when } from "MageObsidian_Customer::js/form-validation";

// Edit-account page entry. Toggles the email / password fieldsets from their
// checkboxes and validates conditionally (the password rules only fire when
// "change password" is ticked). Native POST to editPost; no Pinia here.

function init(): void {
    const form = document.querySelector<HTMLFormElement>("[data-edit-form]");
    if (!form) {
        return;
    }

    const requiredMsg = form.dataset.errRequired;
    const minChars = Number(form.dataset.passwordMinLength || "8");

    // Server renders the fieldsets visible so the no-JS form works; here a fieldset
    // shows when any of its checkboxes is ticked.
    const bindToggle = (checkboxes: string[], target: string): void => {
        const boxes = checkboxes
            .map((name) => form.elements.namedItem(name))
            .filter((el): el is HTMLInputElement => el instanceof HTMLInputElement);
        const fields = form.querySelector<HTMLElement>(target);
        if (boxes.length === 0 || !fields) {
            return;
        }
        const sync = (): void => {
            fields.hidden = !boxes.some((box) => box.checked);
        };
        boxes.forEach((box) => box.addEventListener("change", sync));
        sync();
    };
    bindToggle(["change_email"], "[data-email-fields]");
    bindToggle(["change_password"], "[data-password-fields]");
    bindToggle(["change_email", "change_password"], "[data-current-password-fields]");

    const changingEmail = (all: Record<string, string>): boolean => all.change_email !== "";
    const changingPassword = (all: Record<string, string>): boolean => all.change_password !== "";
    const needsCurrentPassword = (all: Record<string, string>): boolean =>
        changingEmail(all) || changingPassword(all);

    enhanceValidation(form, {
        // Collected (no rules of their own) so the predicates below can read them.
        change_email: [],
        change_password: [],
        firstname: [required(requiredMsg)],
        lastname: [required(requiredMsg)],
        email: [when(changingEmail, required(requiredMsg)), when(changingEmail, email(form.dataset.errEmail))],
        current_password: [when(needsCurrentPassword, required(requiredMsg))],
        password: [
            when(changingPassword, required(requiredMsg)),
            when(changingPassword, minLength(minChars, form.dataset.errMinlength)),
        ],
        password_confirmation: [when(changingPassword, matches("password", form.dataset.errMatch))],
    });
}

init();
