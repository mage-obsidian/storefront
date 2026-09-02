/**
 * Turns filtering, sorting and paging a listing into a fragment exchange.
 *
 * Every control it hijacks is a real link (or a <select> whose options are real
 * URLs), so this is pure enhancement: with no JS, a broken endpoint or
 * ?obsidian_data=0 the same clicks navigate. Only controls that stay on the
 * current path are taken — that is what tells a filter apart from a product
 * card, both of which live inside the listing region.
 */
import events from 'MageObsidian_ModernFrontend::js/events';
import { MutationPhase } from 'mage-obsidian/runtime/mutationEvent.ts';
import {
    ListingOperation,
    listingEvent,
    type ListingNavigateEvent,
} from 'MageObsidian_Storefront::js/listing-events';
import {
    createFragmentStyleAdopter,
    type FragmentStyleAdopter,
} from 'MageObsidian_Storefront::js/fragment-styles';
import {
    CardRole,
    cardNames,
    createListingTransition,
    incomingCardNames,
    tagCards,
    type SwapRunner,
} from 'MageObsidian_Search::js/listing-transition';

const CONFIG_HOOK = 'data-obsidian-listing';
const NAV_SELECT = 'select[data-nav-select]';
const BUSY_ATTRIBUTE = 'aria-busy';
const JSON_MIME = 'application/json';
const FLAG_ON = '1';

/** Two dead exchanges in a row and the listing goes back to plain navigation. */
const FAILURE_BUDGET = 2;

export interface NavigatorConfig {
    parameter: string;
    attribute: string;
    /** Paths a listing control can point at, published by the ViewModel. */
    paths: string[];
}

export interface FragmentPayload {
    sections?: Record<string, string>;
    error?: boolean;
}

export interface NavigatorDeps {
    doc?: Document;
    fetch?: typeof fetch;
    history?: Pick<History, 'pushState'>;
    location?: Pick<Location, 'href' | 'assign'>;
    transition?: SwapRunner;
    styles?: FragmentStyleAdopter;
}

export function readConfig(root: ParentNode = document): NavigatorConfig | null {
    const holder = root.querySelector<HTMLElement>(`[${CONFIG_HOOK}]`);
    const parameter = holder?.dataset.parameter;
    const attribute = holder?.dataset.attribute;
    const paths = (holder?.dataset.paths ?? '').split(' ').filter(Boolean);

    return parameter && attribute && paths.length ? { parameter, attribute, paths } : null;
}

export function bindListingNavigator(config: NavigatorConfig, deps: NavigatorDeps = {}): () => void {
    const doc = deps.doc ?? document;
    const request = deps.fetch ?? globalThis.fetch.bind(globalThis);
    const past = deps.history ?? window.history;
    const here = deps.location ?? window.location;
    const animate = deps.transition ?? createListingTransition(doc);
    const styles = deps.styles ?? createFragmentStyleAdopter(doc);

    const sectionSelector = `[${config.attribute}]`;
    let token = 0;
    let failures = 0;
    let inFlight: AbortController | null = null;

    const section = (name: string): Element | null =>
        doc.querySelector(`[${config.attribute}="${name}"]`);

    const listingPaths = new Set(config.paths);

    // A listing control rewrites the query of a path the ViewModel published;
    // a product card inside the same region points at another path entirely.
    // The paths come from the server because they are not always the one being
    // browsed: search results are served from /catalogsearch/result/ but their
    // filters are built against /catalogsearch/result/index/.
    const staysOnPage = (url: string): boolean => {
        const target = new URL(url, here.href);
        return listingPaths.has(target.pathname) && target.href !== here.href;
    };

    // Sorted so that the same set of filters is one cache object no matter what
    // order the visitor clicked them in.
    const fragmentUrl = (url: string): string => {
        const target = new URL(url, here.href);
        target.searchParams.set(config.parameter, FLAG_ON);
        target.searchParams.sort();

        return target.toString();
    };

    const announce = (phase: MutationPhase, url: string, result?: string[], message?: string): void => {
        void events.dispatch(listingEvent(phase), {
            operation: ListingOperation.Navigate,
            cancelled: false,
            url,
            result,
            message,
        } satisfies ListingNavigateEvent);
    };

    const busy = (state: boolean): void => {
        doc.querySelectorAll(sectionSelector).forEach((element) =>
            state ? element.setAttribute(BUSY_ATTRIBUTE, 'true') : element.removeAttribute(BUSY_ATTRIBUTE),
        );
    };

    // All or nothing: a listing that swapped but kept the old sidebar counts is
    // worse than one that reloaded.
    const swap = (sections: Record<string, string>): string[] => {
        const targets = Object.keys(sections).map((name) => [name, section(name)] as const);
        if (targets.some(([, target]) => target === null)) {
            throw new Error('A listing fragment section has no target in the page.');
        }

        return targets.map(([name, target]) => {
            const parsed = doc.createElement('template');
            parsed.innerHTML = sections[name];
            styles.adopt(parsed.content, name);
            (target as Element).replaceWith(parsed.content);

            return name;
        });
    };

    // Only claws the page back when the visitor has scrolled past what changed —
    // filtering from the top of a category should not jump anywhere.
    const reveal = (changed: string[]): void => {
        const highest = changed
            .map((name) => section(name))
            .filter((element): element is Element => element !== null)
            .sort((a, b) => a.getBoundingClientRect().top - b.getBoundingClientRect().top)[0];

        if (highest && highest.getBoundingClientRect().top < 0) {
            highest.scrollIntoView({ block: 'start' });
        }
    };

    const go = async (url: string, push: boolean): Promise<void> => {
        const ticket = ++token;
        inFlight?.abort();
        const controller = new AbortController();
        inFlight = controller;

        announce(MutationPhase.Before, url);
        busy(true);

        try {
            const response = await request(fragmentUrl(url), {
                headers: { Accept: JSON_MIME },
                credentials: 'same-origin',
                signal: controller.signal,
            });
            if (ticket !== token) {
                return;
            }
            if (!response.ok || !(response.headers.get('content-type') ?? '').includes(JSON_MIME)) {
                throw new Error(`Listing fragment request answered ${response.status}.`);
            }

            const payload = (await response.json()) as FragmentPayload;
            if (ticket !== token) {
                return;
            }
            if (payload.error || !payload.sections) {
                throw new Error('Listing fragment endpoint reported a failure.');
            }

            const incoming = incomingCardNames(doc, payload.sections);
            const outgoing = cardNames(doc);
            tagCards(doc, incoming, CardRole.Exit);

            // Scrolled before the transition opens, so both snapshots share a
            // viewport: a card that survives the filter then morphs a few
            // hundred pixels instead of flying the length of the page.
            reveal(Object.keys(payload.sections));

            const changed = await animate(() => {
                const names = swap(payload.sections as Record<string, string>);
                cardNames(doc);
                tagCards(doc, outgoing, CardRole.Enter);
                return names;
            });
            if (push) {
                past.pushState({}, '', url);
            }
            failures = 0;
            announce(MutationPhase.After, url, changed);
        } catch (error) {
            if (ticket !== token) {
                return;
            }
            failures += 1;
            announce(MutationPhase.Failed, url, undefined, String(error));
            if (failures >= FAILURE_BUDGET) {
                teardown();
            }
            here.assign(url);
        } finally {
            if (ticket === token) {
                busy(false);
                inFlight = null;
            }
        }
    };

    const onClick = (event: MouseEvent): void => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        const link = (event.target as Element | null)?.closest?.<HTMLAnchorElement>('a[href]');
        if (!link || !link.closest(sectionSelector) || link.target || link.hasAttribute('download')) {
            return;
        }
        if (!staysOnPage(link.href)) {
            return;
        }

        event.preventDefault();
        void go(link.href, true);
    };

    // Capture, because the shared nav-select enhancer listens on the element
    // itself: stopping propagation here is what keeps it from navigating too.
    const onChange = (event: Event): void => {
        const select = event.target as HTMLSelectElement | null;
        if (!select?.matches?.(NAV_SELECT) || !select.closest(sectionSelector)) {
            return;
        }
        if (!select.value || !staysOnPage(select.value)) {
            return;
        }

        event.stopPropagation();
        void go(select.value, true);
    };

    const onPopState = (): void => {
        void go(here.href, false);
    };

    const teardown = (): void => {
        doc.removeEventListener('click', onClick);
        doc.removeEventListener('change', onChange, true);
        window.removeEventListener('popstate', onPopState);
    };

    doc.addEventListener('click', onClick);
    doc.addEventListener('change', onChange, true);
    window.addEventListener('popstate', onPopState);

    return teardown;
}

const config = readConfig();
if (config) {
    bindListingNavigator(config);
}
