// This file is part of the MageObsidian - Customer project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { enhanceValidation, required, email } from "MageObsidian_Storefront::js/form-validation";
import { setButtonBusy } from "MageObsidian_Storefront::js/button-state";
import { useAuth, CAPTCHA_FORM_ID } from "MageObsidian_Customer::js/useAuth";
import { useCustomerData } from "MageObsidian_ModernFrontend::js/customer-data";
import { tokenFor, resetToken } from "MageObsidian_Storefront::js/recaptcha";
import "MageObsidian_Storefront::js/captcha-refresh";

// Login page entry (loaded only here, so the customer-data/Pinia cost is paid
// only where it's used). The native <form> POSTs to loginPost without JS; this
// adds inline validation and an AJAX sign-in that refreshes the header/cart
// before redirecting.

const REFERER_IN_ACTION = /\/referer\/[^/]+/;
const CAPTCHA_SECTION = "captcha";
const CAPTCHA_FIELD = "[data-captcha]";
const RECAPTCHA_FORM = "customer_login";

function captchaAnswer(form: HTMLFormElement): string {
    return form.querySelector<HTMLInputElement>(`input[name="captcha[${CAPTCHA_FORM_ID}]"]`)?.value ?? "";
}

async function challengeRequired(): Promise<boolean> {
    const customerData = useCustomerData();
    await customerData.reload([CAPTCHA_SECTION]);
    const section = customerData.section(CAPTCHA_SECTION) as Record<string, { isRequired?: boolean }> | null;
    return section?.[CAPTCHA_FORM_ID]?.isRequired === true;
}

async function revealChallenge(form: HTMLFormElement): Promise<void> {
    if (form.querySelector(CAPTCHA_FIELD) || !(await challengeRequired())) {
        return;
    }
    const response = await fetch(window.location.href, {
        credentials: "same-origin",
        headers: { Accept: "text/html" },
    });
    if (!response.ok) {
        return;
    }
    const page = new DOMParser().parseFromString(await response.text(), "text/html");
    const field = page.querySelector(`[data-login-form] ${CAPTCHA_FIELD}`);
    if (field) {
        form.querySelector('button[type="submit"]')?.before(document.importNode(field, true));
    }
}

function rememberMeTicked(form: HTMLFormElement): boolean {
    return form.querySelector<HTMLInputElement>('input[name="persistent_remember_me"]')?.checked ?? false;
}

function checkoutContext(form: HTMLFormElement): string | undefined {
    return form.querySelector<HTMLInputElement>('input[name="context"]')?.value || undefined;
}

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
                if (REFERER_IN_ACTION.test(form.action)) {
                    setButtonBusy(submit, true);
                    form.submit();
                    return;
                }
                const url = form.dataset.ajaxLogin || form.action;
                if (errorRegion) {
                    errorRegion.textContent = "";
                }
                setButtonBusy(submit, true);

                const result = await login({
                    url,
                    username: values["login[username]"],
                    password: values["login[password]"],
                    // The challenge is only in the form when the platform asked
                    // for one, and the ajax endpoint reads it from the body: a
                    // shopper who answers it correctly is refused if this is not
                    // carried across, while the no-JS form would have let them in.
                    captchaString: captchaAnswer(form),
                    context: checkoutContext(form),
                    rememberMe: rememberMeTicked(form),
                    recaptchaToken: (await tokenFor(RECAPTCHA_FORM)) ?? "",
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
                resetToken(RECAPTCHA_FORM);
                await revealChallenge(form);
                setButtonBusy(submit, false);
            },
        },
    );
}

init();
