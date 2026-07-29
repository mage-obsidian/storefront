/**
 * Scopes cross-document view transitions to the navigations they were meant for.
 *
 * The theme opts every navigation in with `@view-transition { navigation: auto }`.
 * Without per-element names that captures the whole viewport as a single `root`
 * group, and Chrome blends the two snapshots with `plus-lighter` — so leaving a
 * light listing for the dark home washes the page grey for the length of the
 * transition. Only catalog-internal moves earn an animation; everything else is
 * skipped here.
 *
 * The decision runs in `pageswap`, on the outgoing document: it is the only place
 * that knows which element was clicked, and skipping there costs nothing.
 * Deciding in the incoming document would mean a parser-blocking script in the
 * head on every page, which is what `pagereveal` requires.
 */
import events from "MageObsidian_ModernFrontend::js/events";

export const NavigationKind = {
    Product: "product",
    Listing: "listing",
} as const;

export type NavigationKind = (typeof NavigationKind)[keyof typeof NavigationKind] | null;

export const NAVIGATION_EVENT = "navigation_start";

export interface NavigationEvent {
    from: string;
    to: string;
    kind: NavigationKind;
    trigger: Element | null;
}

declare module "mage-obsidian/runtime/eventManager.ts" {
    interface StorefrontEventMap {
        [NAVIGATION_EVENT]: NavigationEvent;
    }
}

export interface NavigationFacts {
    trigger: Element | null;
    from: string;
    to: string;
    isProductPage: boolean;
    isListingPage: boolean;
    cameFrom: string | null;
}

const HERO_NAME = "pdp-hero";
const HERO_MARKER = "data-vt-hero";
const CARD_SELECTOR = ".product-item";
const GALLERY_SELECTOR = ".pdp__gallery-main";
const NAME_PROPERTY = "view-transition-name";
const NAME_NONE = "none";

const parse = (url: string, base?: string): URL | null => {
    try {
        return new URL(url, base ?? undefined);
    } catch {
        return null;
    }
};

const samePage = (a: URL, b: URL): boolean =>
    a.origin === b.origin && a.pathname === b.pathname;

export function classifyNavigation(facts: NavigationFacts): NavigationKind {
    const from = parse(facts.from);
    const to = parse(facts.to, facts.from);
    if (!from || !to || from.origin !== to.origin || from.href === to.href) {
        return null;
    }

    if (facts.trigger?.closest?.(".product-card")) {
        return NavigationKind.Product;
    }

    // Going back to the listing the product page was opened from: the card still
    // carries the hero name, so the morph plays in reverse.
    if (facts.isProductPage && facts.cameFrom) {
        const origin = parse(facts.cameFrom);
        if (origin && origin.href === to.href) {
            return NavigationKind.Product;
        }
    }

    if (facts.trigger?.closest?.(".subcategory-card")) {
        return NavigationKind.Listing;
    }

    // Pagination, sort and layered filters all live inside the listing's content
    // well. Their URLs are not a reliable signal on their own: search moves the
    // path from `.../result/` to `.../result/index/` while staying on the very
    // same listing.
    if (facts.isListingPage && facts.trigger?.closest?.("#maincontent")) {
        return NavigationKind.Listing;
    }

    return samePage(from, to) ? NavigationKind.Listing : null;
}

export function dedupeCardNames(doc: Document): number {
    const seen = new Set<string>();
    let dropped = 0;

    doc.querySelectorAll<HTMLElement>(CARD_SELECTOR).forEach((card) => {
        const name = card.style.getPropertyValue(NAME_PROPERTY);
        if (!name) {
            return;
        }
        if (seen.has(name)) {
            card.style.removeProperty(NAME_PROPERTY);
            dropped += 1;
            return;
        }
        seen.add(name);
    });

    return dropped;
}

export function clearCardNames(doc: Document): void {
    doc.querySelectorAll<HTMLElement>(CARD_SELECTOR).forEach((card) => {
        card.style.removeProperty(NAME_PROPERTY);
    });
}

/**
 * Releases the hero name the PDP stylesheet sets on the gallery, so it stays in
 * the root snapshot.
 *
 * A back navigation has no clicked card to morph towards, so the name would be
 * alone on the outgoing side: its opaque image lands on top of the listing that
 * already painted and only then fades, which reads as the page flashing back.
 */
export function clearHeroName(doc: Document): void {
    const gallery = doc.querySelector<HTMLElement>(GALLERY_SELECTOR);
    if (gallery) {
        gallery.style.setProperty(NAME_PROPERTY, NAME_NONE);
    }
}

/**
 * Moves the hero name onto the clicked card's media box. Returns it so the caller
 * can assert the morph is wired; null when the card has no media box.
 *
 * The box travels, not the <img> inside it: both ends share the aspect ratio,
 * radius and background, so the frame morphs as one piece instead of leaving the
 * PDP gallery's own background showing behind a travelling image.
 */
export function markProductHero(trigger: Element, doc: Document): HTMLElement | null {
    const media = trigger.closest(".product-card")?.querySelector<HTMLElement>(".product-card__media");
    if (!media) {
        return null;
    }

    // A name has to be unique per document: a related-products card clicked from
    // a PDP would otherwise collide with the gallery, which the CSS names too.
    doc.querySelectorAll<HTMLElement>(`[${HERO_MARKER}]`).forEach((marked) => {
        marked.removeAttribute(HERO_MARKER);
        marked.style.viewTransitionName = "";
    });
    const gallery = doc.querySelector<HTMLElement>(GALLERY_SELECTOR);
    if (gallery) {
        gallery.style.viewTransitionName = NAME_NONE;
    }

    media.setAttribute(HERO_MARKER, "");
    media.style.viewTransitionName = HERO_NAME;

    return media;
}

export function bindViewTransitions(win: Window & typeof globalThis): () => void {
    const doc = win.document;
    let trigger: Element | null = null;

    const onClick = (event: Event): void => {
        const target = event.target as Element | null;
        trigger = target?.closest?.("a[href]") ?? null;
    };

    const onPageSwap = (event: Event): void => {
        const swap = event as Event & {
            viewTransition?: { skipTransition: () => void } | null;
            activation?: { entry?: { url?: string } } | null;
        };
        const transition = swap.viewTransition;
        if (!transition) {
            return;
        }

        const nav = (win as unknown as { navigation?: { activation?: { from?: { url?: string } } } })
            .navigation;
        const from = doc.location.href;
        const to = swap.activation?.entry?.url ?? "";
        const kind = classifyNavigation({
            trigger,
            from,
            to,
            isProductPage: doc.querySelector("[data-pdp]") !== null,
            isListingPage: doc.querySelector(".toolbar-products") !== null,
            cameFrom: nav?.activation?.from?.url ?? doc.referrer ?? null,
        });

        void events.dispatch(NAVIGATION_EVENT, { from, to, kind, trigger });

        if (!kind) {
            transition.skipTransition();
            return;
        }

        if (kind === NavigationKind.Product) {
            clearCardNames(doc);
            if (trigger) {
                markProductHero(trigger, doc);
            } else {
                clearHeroName(doc);
            }
            return;
        }

        dedupeCardNames(doc);
    };

    doc.addEventListener("click", onClick, true);
    win.addEventListener("pageswap", onPageSwap);

    return () => {
        doc.removeEventListener("click", onClick, true);
        win.removeEventListener("pageswap", onPageSwap);
    };
}

if (typeof window !== "undefined" && "startViewTransition" in document) {
    bindViewTransitions(window);
}
