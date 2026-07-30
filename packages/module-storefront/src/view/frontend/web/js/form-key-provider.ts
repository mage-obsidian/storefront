/**
 * Ensures a `form_key` cookie exists and that every rendered `form_key` input
 * carries its value. Magento's RegisterFormKeyFromCookie plugin syncs this
 * cookie into the session form key on each request, so add-to-cart POSTs
 * validate even when the page HTML was served from full-page cache — where a
 * form key baked at render time would be stale and rejected as an expired
 * session. This is the modern (no-RequireJS) equivalent of
 * Magento_PageCache/js/form-key-provider, and the single source of truth the
 * cart flow reads its form key from.
 */
const COOKIE = 'form_key';
const LENGTH = 16;
const CHARS = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
const INPUT_SELECTOR = 'input[name="form_key"]';
const COOKIE_LIFETIME_MS = 86400000;

// Magento publishes cookie restrictions as a global; not a standard Window prop.
interface CookiesConfig {
    secure?: boolean;
    samesite?: string;
}

function readCookie(): string {
    const match = typeof document !== 'undefined'
        ? document.cookie.match(/(?:^|;\s*)form_key=([^;]+)/)
        : null;
    return match ? decodeURIComponent(match[1]) : '';
}

function generate(): string {
    const buffer = new Uint32Array(LENGTH);
    if (window.crypto?.getRandomValues) {
        window.crypto.getRandomValues(buffer);
    }
    let key = '';
    for (let i = 0; i < LENGTH; i += 1) {
        const value = buffer[i] || Math.floor(Math.random() * CHARS.length);
        key += CHARS[value % CHARS.length];
    }
    return key;
}

function writeCookie(value: string): void {
    const config = (window as unknown as { cookiesConfig?: CookiesConfig }).cookiesConfig ?? {};
    const secure = config.secure ? '; secure' : '';
    const sameSite = `; samesite=${config.samesite || 'lax'}`;
    const expires = `; expires=${new Date(Date.now() + COOKIE_LIFETIME_MS).toUTCString()}`;
    document.cookie = `${COOKIE}=${value}${expires}; path=/${secure}${sameSite}`;
}

/**
 * Return the current form key, creating the cookie if absent. Reads nothing from
 * the DOM, so markup rendered at any point can ask for the live value instead of
 * carrying one that was correct only when the page was cached.
 */
export function getFormKey(): string {
    const existing = readCookie();
    if (existing) {
        return existing;
    }
    const key = generate();
    writeCookie(key);
    return key;
}

/**
 * Align every form_key input under `root` with the live key, overwriting any
 * stale baked value.
 */
export function stampFormKey(root: ParentNode = document): string {
    const key = getFormKey();
    root.querySelectorAll<HTMLInputElement>(INPUT_SELECTOR).forEach((input) => {
        input.value = key;
    });
    return key;
}

/**
 * Return the current form key and align every rendered input with it.
 */
export function ensureFormKey(): string {
    return stampFormKey(document);
}

ensureFormKey();

// A form that appears after this ran — mounted by an island, or by any markup
// added later — never went through the pass above; and a cookie that expires
// while the tab sits open leaves even a stamped input orphaned, which is the
// "Invalid Form Key" every Magento storefront hits on a long-lived tab. Stamping
// as the form is submitted covers both. Capture phase, so it lands before any
// handler that reads the form. `form.submit()` fires no event and so bypasses
// this; the pass above is what covers that case.
document.addEventListener(
    'submit',
    (event) => {
        if (event.target instanceof HTMLFormElement) {
            stampFormKey(event.target);
        }
    },
    true
);

export default ensureFormKey;
