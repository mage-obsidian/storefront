/**
 * Holds a third-party script until the shopper does something.
 *
 * A tag manager, an analytics library, a chat widget: none of them is needed to
 * paint the page, and every one of them is loaded on the critical path by
 * default. This waits for the first sign of a real visitor — a pointer, a key,
 * a scroll — and loads them then, so the budget the storefront was measured
 * against is the budget it keeps.
 *
 * A script that genuinely cannot wait declares `data-until="now"` and says why
 * in `data-reason`, so a bypass is a visible decision rather than an omission.
 */
export type DeferUntil = "interaction" | "idle" | "now";

const SELECTOR = "[data-deferred-script]";
const SIGNALS = ["pointerdown", "keydown", "touchstart", "wheel", "scroll"] as const;

const armed = new WeakSet<Element>();

export function loadDeferredScript(element: HTMLElement, doc: Document = document): HTMLScriptElement | null {
    const src = element.getAttribute("data-src");
    if (!src || element.dataset.deferredLoaded === "1") {
        return null;
    }
    element.dataset.deferredLoaded = "1";

    const script = doc.createElement("script");
    script.src = src;
    script.async = true;

    let attributes: Record<string, string> = {};
    try {
        attributes = JSON.parse(element.getAttribute("data-attributes") ?? "{}") as Record<string, string>;
    } catch {
        attributes = {};
    }
    Object.entries(attributes).forEach(([name, value]) => script.setAttribute(name, value));

    doc.head.appendChild(script);
    element.dispatchEvent(new CustomEvent("obsidian:deferred-loaded", { bubbles: true, detail: { src } }));
    return script;
}

/**
 * The primitive underneath the declarative form: run something at the moment a
 * deferred script would have loaded. A caller that has to compose its own URL —
 * analytics builds one out of the measurement id — schedules through this rather
 * than reimplementing the waiting.
 */
export function whenReady(until: DeferUntil, run: () => void, view: Window = window): void {
    if (until === "now") {
        run();
        return;
    }
    if (until === "idle") {
        const idle = (view as unknown as { requestIdleCallback?: (cb: () => void) => void }).requestIdleCallback;
        if (idle) {
            idle(run);
        } else {
            view.setTimeout(run, 0);
        }
        return;
    }

    const release = (): void => {
        SIGNALS.forEach((signal) => view.removeEventListener(signal, release));
        run();
    };
    SIGNALS.forEach((signal) => view.addEventListener(signal, release, { passive: true, once: true }));
}

const untilOf = (element: HTMLElement): DeferUntil => {
    const declared = element.getAttribute("data-until");
    return declared === "now" || declared === "idle" ? declared : "interaction";
};

/**
 * One listener for the whole page rather than one per script: the signal is the
 * same for all of them, and a passive listener that removes itself costs
 * nothing after it fires.
 */
export function armDeferredScripts(root: ParentNode = document, view: Window = window): void {
    const doc = (root as Document).defaultView?.document ?? document;
    root.querySelectorAll<HTMLElement>(SELECTOR).forEach((element) => {
        if (armed.has(element)) {
            return;
        }
        armed.add(element);

        whenReady(untilOf(element), () => loadDeferredScript(element, doc), view);
    });
}

armDeferredScripts();
