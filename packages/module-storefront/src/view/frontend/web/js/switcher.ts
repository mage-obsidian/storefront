/**
 * Store / currency switcher behaviour. The markup is a server-rendered
 * `<details>` carrying native GET switch links, so opening, closing, keyboard
 * navigation and the switch itself all work with no JS. This adds only what the
 * native element cannot: closing on outside click and Escape, focusing the first
 * option on open, and defeating the browser's HTTP cache after a switch.
 */
export type SwitchTarget = { action: "reload" } | { action: "assign"; url: string };

export interface SwitchDeps {
    fetch: (url: string, init?: RequestInit) => Promise<{ url?: string }>;
    currentUrl: () => string;
    assign: (url: string) => void;
    reload: () => void;
}

export function isPlainClick(event: MouseEvent): boolean {
    return (
        event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey
    );
}

// The native switch URLs are GET redirects back to a cacheable page, so with the
// built-in FPC the browser can serve the pre-switch page from its own HTTP cache
// and the change only shows after a manual reload. Follow the redirect first,
// then navigate in a way that revalidates.
export function resolveSwitchTarget(responseUrl: string, currentUrl: string): SwitchTarget {
    return responseUrl && responseUrl !== currentUrl
        ? { action: "assign", url: responseUrl }
        : { action: "reload" };
}

function close(details: HTMLDetailsElement, returnFocus: boolean): void {
    details.open = false;
    if (returnFocus) {
        details.querySelector("summary")?.focus();
    }
}

async function switchTo(url: string, deps: SwitchDeps): Promise<void> {
    try {
        const response = await deps.fetch(url, { credentials: "same-origin", redirect: "follow" });
        const target = resolveSwitchTarget(response.url ?? "", deps.currentUrl());
        if (target.action === "assign") {
            deps.assign(target.url);
        } else {
            deps.reload();
        }
    } catch {
        deps.assign(url);
    }
}

export function bindSwitchers(root: Document | HTMLElement, deps: SwitchDeps): void {
    root.addEventListener("click", (event) => {
        const target = event.target as HTMLElement | null;

        for (const details of root.querySelectorAll<HTMLDetailsElement>("[data-switcher][open]")) {
            if (!details.contains(target)) {
                close(details, false);
            }
        }

        const link = target?.closest?.<HTMLAnchorElement>("a[data-switch-link]");
        if (!link || !isPlainClick(event as MouseEvent)) {
            return;
        }
        event.preventDefault();
        const details = link.closest<HTMLDetailsElement>("[data-switcher]");
        if (details) {
            close(details, false);
        }
        void switchTo(link.href, deps);
    });

    root.addEventListener("keydown", (event) => {
        if ((event as KeyboardEvent).key !== "Escape") {
            return;
        }
        const details = (event.target as HTMLElement | null)?.closest?.<HTMLDetailsElement>(
            "[data-switcher][open]",
        );
        if (details) {
            close(details, true);
        }
    });

    for (const details of root.querySelectorAll<HTMLDetailsElement>("[data-switcher]")) {
        details.addEventListener("toggle", () => {
            if (details.open) {
                details.querySelector<HTMLAnchorElement>("a[data-switch-link]")?.focus();
            }
        });
    }
}

export const browserDeps: SwitchDeps = {
    fetch: (url, options) => globalThis.fetch(url, options),
    currentUrl: () => window.location.href,
    assign: (url) => window.location.assign(url),
    reload: () => window.location.reload(),
};
