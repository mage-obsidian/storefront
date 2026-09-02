import { i18n } from "mage-obsidian/runtime/i18nCore.ts";

export interface CarouselLabels {
    carousel: string;
    skip: string;
    position: (index: number, total: number) => string;
}

const DEFAULT_LABELS: CarouselLabels = {
    carousel: "Product carousel",
    skip: "Skip the carousel",
    position: (index, total) => `Product ${index} of ${total}`,
};

const STRIP = "[data-carousel-strip], .product-items";
const ITEM = "[data-carousel-item], .product-item";

const bound = new WeakSet<Element>();

let sequence = 0;

export function stripOf(root: Element): HTMLElement | null {
    return root.matches(STRIP) ? (root as HTMLElement) : root.querySelector<HTMLElement>(STRIP);
}

export function itemsOf(strip: Element): HTMLElement[] {
    return Array.from(strip.querySelectorAll<HTMLElement>(ITEM)).filter(
        (item) => item.closest(STRIP) === strip,
    );
}

export function stepFor(strip: HTMLElement, items: HTMLElement[], direction: number): number {
    if (items.length === 0) {
        return strip.scrollLeft;
    }

    const current = items.reduce(
        (closest, item, index) =>
            Math.abs(item.offsetLeft - strip.scrollLeft) <
            Math.abs(items[closest].offsetLeft - strip.scrollLeft)
                ? index
                : closest,
        0,
    );
    const next = Math.min(Math.max(current + direction, 0), items.length - 1);

    return items[next].offsetLeft - strip.offsetLeft;
}

function announcer(root: HTMLElement, doc: Document): HTMLElement {
    const existing = root.querySelector<HTMLElement>("[data-carousel-status]");
    if (existing) {
        return existing;
    }

    const status = doc.createElement("p");
    status.setAttribute("data-carousel-status", "");
    status.setAttribute("aria-live", "polite");
    root.appendChild(status);

    return status;
}

function skipLink(root: HTMLElement, doc: Document, label: string): HTMLAnchorElement | null {
    if (root.querySelector("[data-carousel-skip]")) {
        return null;
    }

    sequence += 1;
    const anchorId = `carousel-end-${sequence}`;
    const end = doc.createElement("span");
    end.id = anchorId;
    end.setAttribute("tabindex", "-1");
    root.appendChild(end);

    const skip = doc.createElement("a");
    skip.href = `#${anchorId}`;
    skip.textContent = label;
    skip.setAttribute("data-carousel-skip", "");
    root.insertBefore(skip, root.firstChild);

    return skip;
}

export function enhanceProductCarousel(
    root: HTMLElement,
    view: Window = window,
    labels: Partial<CarouselLabels> = {},
): boolean {
    if (bound.has(root)) {
        return false;
    }

    const strip = stripOf(root);
    if (strip === null) {
        return false;
    }

    const items = itemsOf(strip);
    if (items.length === 0) {
        return false;
    }

    bound.add(root);
    const text = { ...DEFAULT_LABELS, ...labels };
    const doc = view.document;

    root.setAttribute("role", "group");
    root.setAttribute("aria-roledescription", "carousel");
    if (!root.hasAttribute("aria-label")) {
        root.setAttribute("aria-label", text.carousel);
    }
    strip.setAttribute("data-carousel-strip", "");
    strip.setAttribute("tabindex", "0");
    items.forEach((item, index) => {
        item.setAttribute("data-carousel-item", "");
        item.setAttribute("role", "group");
        item.setAttribute("aria-roledescription", "slide");
        item.setAttribute("aria-label", text.position(index + 1, items.length));
    });

    const status = announcer(root, doc);
    skipLink(root, doc, text.skip);

    strip.addEventListener("keydown", (event) => {
        const key = (event as KeyboardEvent).key;
        const direction = key === "ArrowRight" ? 1 : key === "ArrowLeft" ? -1 : 0;
        if (direction === 0 || event.target !== strip) {
            return;
        }

        event.preventDefault();
        strip.scrollTo({ left: stepFor(strip, items, direction), behavior: "smooth" });
    });

    strip.addEventListener("scroll", () => {
        const nearest =
            items.reduce(
                (closest, item, index) =>
                    Math.abs(item.offsetLeft - strip.scrollLeft) <
                    Math.abs(items[closest].offsetLeft - strip.scrollLeft)
                        ? index
                        : closest,
                0,
            ) + 1;
        status.textContent = text.position(nearest, items.length);
    });

    return true;
}

const NATIVE_CAROUSEL = '[data-content-type="products"][data-appearance="carousel"]';

export function enhanceProductCarousels(root: ParentNode = document, view: Window = window): void {
    root.querySelectorAll<HTMLElement>(NATIVE_CAROUSEL).forEach((element) =>
        enhanceProductCarousel(element, view, {
            carousel: i18n.$t("Product carousel"),
            skip: i18n.$t("Skip the carousel"),
            position: (index, total) => `${i18n.$t("Product")} ${index}/${total}`,
        }),
    );
}

enhanceProductCarousels();
