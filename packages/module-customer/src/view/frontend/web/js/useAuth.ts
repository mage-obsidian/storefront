import { useCustomerData } from "MageObsidian_ModernFrontend::js/customer-data";
import ensureFormKey from "MageObsidian_Storefront::js/form-key-provider";

// AJAX sign-in over Magento's native `customer/ajax/login` endpoint — the
// progressive enhancement layered on top of the native loginPost form. On
// success it refreshes customer-data so the header account state and cart badge
// reflect the new session without a full page reload, then hands the caller a
// redirect target. Reused by the login enhancer (and, later, an inline/modal
// login). The native <form> remains the fallback when JS is unavailable.

export interface LoginRequest {
    /** Full URL of the customer/ajax/login endpoint (resolved server-side). */
    url: string;
    username: string;
    password: string;
    /** Magento login context; "default" unless signing in mid-checkout. */
    context?: string;
}

export interface LoginResult {
    ok: boolean;
    message?: string;
    /** Where the server wants the browser to land after a successful sign-in. */
    redirectUrl?: string;
}

interface AjaxLoginResponse {
    errors?: boolean;
    message?: string;
    redirectUrl?: string;
}

export function useAuth() {
    const customerData = useCustomerData();

    async function login(req: LoginRequest): Promise<LoginResult> {
        // Keeps the form_key cookie alive for the native fallback form; the ajax
        // endpoint authenticates on the credentials themselves.
        ensureFormKey();

        let payload: AjaxLoginResponse = {};
        let ok = false;
        try {
            const response = await fetch(req.url, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: JSON.stringify({
                    username: req.username,
                    password: req.password,
                    context: req.context ?? "default",
                }),
                credentials: "same-origin",
            });
            payload = (await response.json().catch(() => ({}))) as AjaxLoginResponse;
            ok = response.ok && payload.errors !== true;
        } catch {
            ok = false;
        }

        // Refresh sections only on success; a failed attempt leaves state intact.
        if (ok) {
            await customerData.reload();
        }

        return { ok, message: payload.message, redirectUrl: payload.redirectUrl };
    }

    return { login };
}

export default useAuth;
