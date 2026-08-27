const FIELD = "[data-captcha]";
const IMAGE = "[data-captcha-image]";
const RELOAD = "[data-captcha-reload]";

const bound = new WeakSet<EventTarget>();

async function refresh(field: HTMLElement, fetcher: typeof fetch): Promise<string | null> {
    const url = field.getAttribute("data-captcha-refresh");
    const formId = field.getAttribute("data-captcha");
    const image = field.querySelector<HTMLImageElement>(IMAGE);
    if (!url || !formId || !image) {
        return null;
    }

    // Magento answers a POST with no form key with a 302 unless it recognises the
    // request as XHR, and this endpoint carries no form key at all — the header is
    // what makes the platform skip the CSRF redirect, exactly as its own jQuery
    // caller got for free.
    const response = await fetcher(url, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
        },
        credentials: "same-origin",
        body: JSON.stringify({ formId }),
    });
    if (!response.ok) {
        return null;
    }

    const payload = (await response.json()) as { imgSrc?: string };
    if (!payload?.imgSrc) {
        return null;
    }

    image.src = payload.imgSrc;
    return payload.imgSrc;
}

export function bindCaptchaReload(root: ParentNode & EventTarget = document, fetcher: typeof fetch = fetch): void {
    if (bound.has(root)) {
        return;
    }
    bound.add(root);

    root.addEventListener("click", (event) => {
        const trigger = (event.target as Element | null)?.closest?.(RELOAD);
        if (!trigger) {
            return;
        }

        const field = trigger.closest<HTMLElement>(FIELD);
        if (!field) {
            return;
        }

        event.preventDefault();
        void refresh(field, fetcher);
    });
}

export { refresh as refreshCaptcha };

bindCaptchaReload();
