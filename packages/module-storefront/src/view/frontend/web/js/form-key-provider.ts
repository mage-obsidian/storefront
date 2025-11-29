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
    const expires = `; expires=${new Date(Date.now() + 86400000).toUTCString()}`;
    document.cookie = `${COOKIE}=${value}${expires}; path=/${secure}${sameSite}`;
}

/**
 * Return the current form key, creating the cookie if absent, and align every
 * rendered form_key input with it (overwriting any stale baked value).
 */
export function ensureFormKey(): string {
    let key = readCookie();
    if (!key) {
        key = generate();
        writeCookie(key);
    }
    document.querySelectorAll<HTMLInputElement>('input[name="form_key"]').forEach((input) => {
        input.value = key;
    });
    return key;
}

ensureFormKey();

export default ensureFormKey;
