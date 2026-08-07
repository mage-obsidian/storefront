import { enhanceValidation, required, email } from "MageObsidian_Storefront::js/form-validation";
import { setButtonBusy } from "MageObsidian_Storefront::js/button-state";
import { useAuth } from "MageObsidian_Customer::js/useAuth";

// Login page entry (loaded only here, so the customer-data/Pinia cost is paid
// only where it's used). The native <form> POSTs to loginPost without JS; this
// adds inline validation and an AJAX sign-in that refreshes the header/cart
// before redirecting.

function init(): void {
    const form = document.querySelector<HTMLFormElement>("[data-login-form]");
    if (!form) {
        return;
    }

    const { login } = useAuth();
    const errorRegion = form.querySelector<HTMLElement>("[data-login-error]");
    const submit = form.querySelector<HTMLButtonElement>('button[type="submit"]');

    const requiredMsg = form.dataset.errRequired;
    const emailMsg = form.dataset.errEmail;

    enhanceValidation(
        form,
        {
            "login[username]": [required(requiredMsg), email(emailMsg)],
            "login[password]": [required(requiredMsg)],
        },
        {
            onValidSubmit: async (values) => {
                const url = form.dataset.ajaxLogin || form.action;
                if (errorRegion) {
                    errorRegion.textContent = "";
                }
                setButtonBusy(submit, true);

                const result = await login({
                    url,
                    username: values["login[username]"],
                    password: values["login[password]"],
                });

                if (result.ok) {
                    window.location.assign(result.redirectUrl || form.dataset.redirect || form.action);
                    return;
                }

                if (errorRegion) {
                    errorRegion.textContent = result.message
                        || form.dataset.errFailed
                        || "We couldn't sign you in. Check your details and try again.";
                }
                setButtonBusy(submit, false);
            },
        },
    );
}

init();
