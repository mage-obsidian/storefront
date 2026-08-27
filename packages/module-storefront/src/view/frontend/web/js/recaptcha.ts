export interface ReCaptchaSettings {
    formKey: string;
    sitekey: string;
    size: string;
    theme: string;
    badge: string;
    lang: string;
    invisible: boolean;
}

interface RenderParameters {
    sitekey: string;
    size?: string;
    theme?: string;
    badge?: string;
    callback?: (token: string) => void;
    "expired-callback"?: () => void;
}

export interface GReCaptcha {
    render(element: HTMLElement, parameters: RenderParameters): number;
    execute(widgetId: number): void;
    reset(widgetId: number): void;
    getResponse(widgetId: number): string;
}

const API = "https://www.google.com/recaptcha/api.js";
const READY = "mageObsidianReCaptchaReady";
const FIELD = "[data-recaptcha]";

type Holder = Window & { grecaptcha?: GReCaptcha; [key: string]: unknown };

interface Widget {
    id: number;
    settings: ReCaptchaSettings;
    element: HTMLElement;
    pending: ((token: string) => void)[];
}

const widgets = new Map<string, Widget>();
const bound = new WeakSet<EventTarget>();
let loading: Promise<GReCaptcha> | null = null;

export function resetReCaptchaModule(): void {
    widgets.clear();
    loading = null;
}

export function loadReCaptchaApi(lang = "", doc: Document = document): Promise<GReCaptcha> {
    if (loading) {
        return loading;
    }

    loading = new Promise<GReCaptcha>((resolve, reject) => {
        const holder = doc.defaultView as unknown as Holder | null;
        if (!holder) {
            reject(new Error("recaptcha: no window to load into"));
            return;
        }
        if (holder.grecaptcha?.render) {
            resolve(holder.grecaptcha);
            return;
        }

        holder[READY] = () => {
            if (holder.grecaptcha?.render) {
                resolve(holder.grecaptcha);
            } else {
                reject(new Error("recaptcha: the API loaded without a renderer"));
            }
        };

        const script = doc.createElement("script");
        const language = lang ? `&hl=${encodeURIComponent(lang)}` : "";
        script.src = `${API}?render=explicit&onload=${READY}${language}`;
        script.async = true;
        script.defer = true;
        script.addEventListener("error", () => reject(new Error("recaptcha: the API could not be reached")));
        doc.head.appendChild(script);
    });

    return loading;
}

function settingsOf(element: HTMLElement): ReCaptchaSettings | null {
    const raw = element.getAttribute("data-recaptcha");
    if (!raw) {
        return null;
    }

    try {
        const parsed = JSON.parse(raw) as ReCaptchaSettings;
        return parsed?.sitekey && parsed?.formKey ? parsed : null;
    } catch {
        return null;
    }
}

function settle(widget: Widget, token: string): void {
    const waiting = widget.pending.splice(0, widget.pending.length);
    waiting.forEach((resolve) => resolve(token));
}

export async function mountReCaptcha(element: HTMLElement, doc: Document = document): Promise<Widget | null> {
    const settings = settingsOf(element);
    if (!settings || widgets.has(settings.formKey)) {
        return settings ? (widgets.get(settings.formKey) ?? null) : null;
    }

    const api = await loadReCaptchaApi(settings.lang, doc);
    const widget: Widget = { id: -1, settings, element, pending: [] };

    widget.id = api.render(element, {
        sitekey: settings.sitekey,
        size: settings.invisible ? "invisible" : settings.size || "normal",
        theme: settings.theme || "light",
        ...(settings.badge ? { badge: settings.badge } : {}),
        callback: (token: string) => settle(widget, token),
        "expired-callback": () => settle(widget, ""),
    });

    widgets.set(settings.formKey, widget);
    return widget;
}

/**
 * The token for a form, executing an invisible challenge if that is what the
 * store configured. Null when the form carries no challenge at all, which is
 * what a caller sends when the platform is not asking for one.
 */
export async function tokenFor(formKey: string, doc: Document = document): Promise<string | null> {
    const widget = widgets.get(formKey);
    if (!widget) {
        return null;
    }

    const api = await loadReCaptchaApi(widget.settings.lang, doc);
    const existing = api.getResponse(widget.id);
    if (existing) {
        return existing;
    }
    if (!widget.settings.invisible) {
        return "";
    }

    return new Promise<string>((resolve) => {
        widget.pending.push(resolve);
        api.execute(widget.id);
    });
}

export function resetToken(formKey: string, doc: Document = document): void {
    const widget = widgets.get(formKey);
    if (widget) {
        void loadReCaptchaApi(widget.settings.lang, doc).then((api) => api.reset(widget.id));
    }
}

/**
 * An invisible challenge has nothing for the shopper to click, so the token has
 * to be fetched between the submit and the POST. A visible one already wrote its
 * token into the form, and is left alone.
 */
function guardInvisibleSubmits(root: ParentNode & EventTarget, doc: Document): void {
    root.addEventListener(
        "submit",
        (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            const element = form.querySelector<HTMLElement>(FIELD);
            const settings = element ? settingsOf(element) : null;
            if (!settings?.invisible || form.dataset.recaptchaCleared === "1") {
                return;
            }

            event.preventDefault();
            void tokenFor(settings.formKey, doc).then(() => {
                form.dataset.recaptchaCleared = "1";
                form.requestSubmit();
            });
        },
        true,
    );
}

export function bindReCaptchaFields(root: ParentNode & EventTarget = document, doc: Document = document): void {
    if (bound.has(root)) {
        return;
    }
    bound.add(root);

    guardInvisibleSubmits(root, doc);
    root.querySelectorAll<HTMLElement>(FIELD).forEach((element) => {
        void mountReCaptcha(element, doc).catch(() => undefined);
    });
}

bindReCaptchaFields();
