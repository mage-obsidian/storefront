/**
 * Cookie-restriction consent banner enhancer. The server renders the banner
 * hidden; this reveals it only when the `user_allowed_save_cookie` cookie is
 * absent. On "Allow", it writes that cookie with the accepted website ids (the
 * exact value Magento's `Cookie::isUserNotAllowSaveCookie` read-path decodes) and
 * hides the banner; if the browser refuses the cookie, it falls back to the
 * no-cookies URL. No jQuery / mage widget — a single delegated listener.
 */

const ALLOW_EVENT = "user:allowed:save:cookie";

/** Read a cookie value (URL-decoded) from a cookie string, or null. */
export function readCookie(name: string, cookieString: string): string | null {
    const prefix = `${name}=`;
    for (const part of cookieString.split(";")) {
        const entry = part.trim();
        if (entry.startsWith(prefix)) {
            return decodeURIComponent(entry.slice(prefix.length));
        }
    }
    return null;
}

/** Serialize the consent cookie the way Magento's read-path expects. */
export function consentCookie(name: string, value: string, lifetime: number): string {
    const maxAge = lifetime > 0 ? `; max-age=${lifetime}` : "";
    return `${name}=${encodeURIComponent(value)}; path=/${maxAge}; samesite=lax`;
}

export function bindCookieNotice(banner: HTMLElement, doc: Document = document): void {
    const name = banner.dataset.cookieName ?? "";
    const value = banner.dataset.cookieValue ?? "";
    const lifetime = Number(banner.dataset.cookieLifetime ?? "0");
    const noCookiesUrl = banner.dataset.noCookiesUrl ?? "";

    if (readCookie(name, doc.cookie) !== null) {
        return;
    }
    banner.hidden = false;

    banner.querySelector<HTMLElement>("[data-cookie-allow]")?.addEventListener("click", () => {
        doc.cookie = consentCookie(name, value, lifetime);
        if (readCookie(name, doc.cookie) !== null) {
            banner.hidden = true;
            doc.dispatchEvent(new CustomEvent(ALLOW_EVENT));
        } else if (noCookiesUrl) {
            window.location.href = noCookiesUrl;
        }
    });
}

function init(): void {
    const banner = document.querySelector<HTMLElement>("[data-cookie-notice]");
    if (banner) {
        bindCookieNotice(banner);
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}
