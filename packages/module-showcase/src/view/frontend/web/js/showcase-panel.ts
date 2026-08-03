/**
 * Drives the demo's feature switchboard.
 *
 * Picking a value never just sets the cookie and reloads. The full page cache
 * resolves its key before the request has been dispatched, so on that reload it
 * would still be keyed on the profile the visitor just left and hand back the
 * page they were trying to change. Navigating through the parameter is a URL the
 * cache has never seen, which renders the new profile and, on the way out,
 * stamps the vary cookie every later navigation is keyed on.
 */

const PANEL_HOOK = 'data-showcase';
const TOGGLE = '[data-showcase-toggle]';
const PANEL = '[data-showcase-panel], #showcase-panel';
const FEATURE = '[data-showcase-feature]';
const RESET = '[data-showcase-reset]';
const SHARE = '[data-showcase-share]';
const EXPANDED = 'aria-expanded';
const OPEN_CLASS = 'showcase--open';
const PAIR_SEPARATOR = '~';
const VALUE_SEPARATOR = '=';
const COOKIE_MAX_AGE = 60 * 60 * 24 * 30;

export interface PanelConfig {
    parameter: string;
    cookie: string;
    signature: string;
}

export interface PanelDeps {
    doc?: Document;
    location?: Pick<Location, 'href' | 'assign'>;
    history?: Pick<History, 'replaceState'>;
    clipboard?: Pick<Clipboard, 'writeText'>;
}

export function readPanelConfig(root: ParentNode = document): PanelConfig | null {
    const holder = root.querySelector<HTMLElement>(`[${PANEL_HOOK}]`);
    const parameter = holder?.dataset.parameter;
    const cookie = holder?.dataset.cookie;

    return parameter && cookie ? { parameter, cookie, signature: holder?.dataset.signature ?? '' } : null;
}

/** Canonical, so the same set of choices is one cache entry however it was reached. */
export function encodeProfile(selections: Record<string, string>): string {
    return Object.keys(selections)
        .sort()
        .map((key) => `${key}${VALUE_SEPARATOR}${selections[key]}`)
        .join(PAIR_SEPARATOR);
}

export function profileUrl(href: string, parameter: string, profile: string): string {
    const target = new URL(href);
    if (profile === '') {
        target.searchParams.delete(parameter);
    } else {
        target.searchParams.set(parameter, profile);
    }

    return target.toString();
}

export function bindShowcasePanel(config: PanelConfig, deps: PanelDeps = {}): () => void {
    const doc = deps.doc ?? document;
    const here = deps.location ?? window.location;
    const past = deps.history ?? window.history;

    const root = doc.querySelector<HTMLElement>(`[${PANEL_HOOK}]`);
    const drawer = doc.querySelector<HTMLElement>(PANEL);
    const toggle = doc.querySelector<HTMLElement>(TOGGLE);
    if (!root || !drawer || !toggle) {
        return () => {};
    }

    const remember = (profile: string): void => {
        const value = profile === '' ? '; Max-Age=0' : `; Max-Age=${COOKIE_MAX_AGE}`;
        doc.cookie = `${config.cookie}=${encodeURIComponent(profile)}; Path=/; SameSite=Lax${value}`;
    };

    // Only what departs from the store view travels. Sending a value that
    // merely repeats the store's own would grow the cookie, the shared link and
    // the number of distinct cache entries for no change at all.
    const chosen = (): Record<string, string> => {
        const selections: Record<string, string> = {};
        doc.querySelectorAll<HTMLSelectElement>(FEATURE).forEach((select) => {
            const key = select.dataset.showcaseFeature;
            if (key && select.value !== select.dataset.showcaseDefault) {
                selections[key] = select.value;
            }
        });

        return selections;
    };

    const go = (profile: string): void => {
        remember(profile);
        here.assign(profileUrl(here.href, config.parameter, profile));
    };

    const onToggle = (): void => {
        const open = toggle.getAttribute(EXPANDED) === 'true';
        toggle.setAttribute(EXPANDED, String(!open));
        drawer.hidden = open;
        root.classList.toggle(OPEN_CLASS, !open);
    };

    const onChange = (event: Event): void => {
        const select = event.target as HTMLSelectElement | null;
        if (select?.matches?.(FEATURE)) {
            go(encodeProfile(chosen()));
        }
    };

    const onClick = (event: Event): void => {
        const target = event.target as Element | null;
        if (target?.closest?.(RESET)) {
            go('');
            return;
        }
        if (target?.closest?.(SHARE)) {
            void (deps.clipboard ?? navigator.clipboard)?.writeText(
                profileUrl(here.href, config.parameter, encodeProfile(chosen())),
            );
        }
    };

    // The shared link did its job the moment the server read it; leaving it in
    // the address bar would make every URL the visitor copies from here carry a
    // profile they may since have changed.
    const tidy = (): void => {
        const url = new URL(here.href);
        if (url.searchParams.has(config.parameter)) {
            remember(config.signature);
            url.searchParams.delete(config.parameter);
            past.replaceState({}, '', url.toString());
        }
    };

    toggle.addEventListener('click', onToggle);
    doc.addEventListener('change', onChange);
    doc.addEventListener('click', onClick);
    tidy();

    return () => {
        toggle.removeEventListener('click', onToggle);
        doc.removeEventListener('change', onChange);
        doc.removeEventListener('click', onClick);
    };
}

const config = readPanelConfig();
if (config) {
    bindShowcasePanel(config);
}
