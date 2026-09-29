import { i18n } from "mage-obsidian/runtime/i18nCore.ts";

const ATTRIBUTE = "data-navigation-progress";
const ACTIVE = "data-active";
const STILL = "data-still";
const REDUCED_MOTION = "(prefers-reduced-motion: reduce)";

interface NavigateEventLike extends Event {
    destination: { url: string; sameDocument: boolean };
    hashChange: boolean;
    downloadRequest: string | null;
}

export function leavesThePage(event: NavigateEventLike, origin: string): boolean {
    if (event.destination.sameDocument || event.hashChange || event.downloadRequest !== null) {
        return false;
    }

    try {
        return new URL(event.destination.url).origin === origin;
    } catch {
        return false;
    }
}

function createIndicator(doc: Document): HTMLElement {
    const indicator = doc.createElement("div");
    indicator.className = "navigation-progress";
    indicator.setAttribute(ATTRIBUTE, "");
    indicator.setAttribute("role", "status");
    indicator.setAttribute("aria-live", "polite");
    doc.body.appendChild(indicator);

    return indicator;
}

export function bindNavigationProgress(win: Window & typeof globalThis): () => void {
    const navigation = (win as unknown as { navigation?: EventTarget }).navigation;
    if (!navigation) {
        return () => {};
    }

    const doc = win.document;
    const indicator = createIndicator(doc);

    const show = (): void => {
        indicator.toggleAttribute(STILL, win.matchMedia?.(REDUCED_MOTION).matches === true);
        indicator.setAttribute(ACTIVE, "");
        indicator.textContent = i18n.$t("Loading the page");
    };

    const hide = (): void => {
        indicator.removeAttribute(ACTIVE);
        indicator.textContent = "";
    };

    const onNavigate = (event: Event): void => {
        if (leavesThePage(event as NavigateEventLike, win.location.origin)) {
            show();
            return;
        }
        hide();
    };

    const onPageShow = (event: Event): void => {
        if ((event as PageTransitionEvent).persisted) {
            hide();
        }
    };

    navigation.addEventListener("navigate", onNavigate);
    navigation.addEventListener("navigateerror", hide);
    win.addEventListener("pageshow", onPageShow);

    return () => {
        navigation.removeEventListener("navigate", onNavigate);
        navigation.removeEventListener("navigateerror", hide);
        win.removeEventListener("pageshow", onPageShow);
        indicator.remove();
    };
}

if (typeof window !== "undefined") {
    bindNavigationProgress(window);
}
