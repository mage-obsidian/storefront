/**
 * Same-document view transition for a fragment swap.
 *
 * The theme opts every navigation into cross-document transitions, so before
 * this module a filter click animated for free. Turning that click into a DOM
 * swap removed the navigation, and with it the animation. This puts the
 * choreography back on the same terms: the cards already carry a stable
 * `product-<id>` name, so the ones that survive a filter morph into their new
 * slot, and everything the swap did not touch is held still by the class this
 * holds on the root for the length of the transition.
 */

import { declaredValues } from 'MageObsidian_Storefront::js/fragment-styles';

const CARD_SELECTOR = '.product-item';
const MODAL_SELECTOR = 'dialog[open]';
const NAME_PROPERTY = 'view-transition-name';
const CLASS_PROPERTY = 'view-transition-class';
const CARD_CLASS = 'obsidian-card';
const NAME_NONE = 'none';

export const SWAP_CLASS = 'obsidian-listing-swap';

export const CardRole = {
    Enter: 'obsidian-card-enter',
    Exit: 'obsidian-card-exit',
} as const;

export type CardRole = (typeof CardRole)[keyof typeof CardRole];

export interface ViewTransitionLike {
    updateCallbackDone: Promise<void>;
    ready: Promise<void>;
    finished: Promise<void>;
}

export type TransitionStarter = (update: () => void) => ViewTransitionLike;

export type SwapRunner = <T>(update: () => T) => Promise<T>;

function nameOf(card: Element, doc: Document): string {
    const value = doc.defaultView?.getComputedStyle(card).getPropertyValue(NAME_PROPERTY) ?? '';

    return value === NAME_NONE ? '' : value;
}

/**
 * Collects the card names in a subtree and strips repeats as it goes: a name
 * that appears twice in one document aborts the whole transition, and a listing
 * can legitimately show the same product as a related-products widget.
 */
export function cardNames(doc: Document): Set<string> {
    const seen = new Set<string>();

    doc.querySelectorAll<HTMLElement>(CARD_SELECTOR).forEach((card) => {
        const name = nameOf(card, doc);
        if (!name) {
            return;
        }
        if (seen.has(name)) {
            card.style.setProperty(NAME_PROPERTY, NAME_NONE);
            return;
        }
        seen.add(name);
    });

    return seen;
}

/**
 * Tags every card whose name is missing from `survivors`. A card present on both
 * sides of the swap keeps the bare class and gets the default cross-fade while
 * its group morphs; only the ones arriving or leaving earn their own animation.
 */
export function tagCards(doc: Document, survivors: Set<string>, role: CardRole): number {
    let tagged = 0;

    doc.querySelectorAll<HTMLElement>(CARD_SELECTOR).forEach((card) => {
        const name = nameOf(card, doc);
        if (!name || survivors.has(name)) {
            return;
        }
        card.style.setProperty(CLASS_PROPERTY, `${CARD_CLASS} ${role}`);
        tagged += 1;
    });

    return tagged;
}

/** Reads the card names out of fragment HTML without touching the live DOM. */
export function incomingCardNames(doc: Document, sections: Record<string, string>): Set<string> {
    const template = doc.createElement('template');
    template.innerHTML = Object.values(sections).join('');
    const css = Array.from(template.content.querySelectorAll('style'))
        .map((block) => block.textContent ?? '')
        .join('\n');

    return declaredValues(css, NAME_PROPERTY);
}

const starter = (doc: Document): TransitionStarter | null => {
    const start = (doc as Document & { startViewTransition?: TransitionStarter }).startViewTransition;

    return typeof start === 'function' ? start.bind(doc) : null;
};

export function createListingTransition(doc: Document): SwapRunner {
    const start = starter(doc);
    const root = doc.documentElement;
    let active = 0;

    // Clicking a second filter mid-animation makes the browser skip the first
    // transition, and its `finished` still settles: dropping the class there
    // would strip the scoping from the one still running.
    const leave = (): void => {
        active = Math.max(0, active - 1);
        if (active === 0) {
            root.classList.remove(SWAP_CLASS);
        }
    };

    return async <T>(update: () => T): Promise<T> => {
        // A modal <dialog> is painted in the top layer, which lands in neither
        // snapshot: transitioning around an open filter drawer would blink it
        // out of existence for as long as the animation runs.
        if (!start || doc.querySelector(MODAL_SELECTOR)) {
            return update();
        }

        active += 1;
        root.classList.add(SWAP_CLASS);

        let outcome!: T;
        const transition = start(() => {
            outcome = update();
        });

        // A skipped transition rejects `ready` and nothing observes it.
        transition.ready?.catch?.(() => {});
        void transition.finished.then(leave, leave);

        await transition.updateCallbackDone;

        return outcome;
    };
}
