// This file is part of the MageObsidian - Catalog project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
const SWATCH = "a.listing-swatch";
const CARD = ".product-card";
const SELECTED = "aria-current";

export function selection(card: Element): Array<[string, string]> {
    return Array.from(card.querySelectorAll<HTMLElement>(`[data-listing-swatches] [data-attribute-code]`)).flatMap((group) => {
        const chosen = group.querySelector<HTMLElement>(`${SWATCH}[${SELECTED}="true"]`);
        return chosen ? [[group.dataset.attributeCode ?? "", chosen.dataset.optionId ?? ""] as [string, string]] : [];
    });
}

export function selectionHash(card: Element): string {
    const chosen = selection(card);
    return chosen.length ? `#${chosen.map(([code, id]) => `${code}=${id}`).join("&")}` : "";
}

function withoutHash(href: string): string {
    const at = href.indexOf("#");
    return at === -1 ? href : href.slice(0, at);
}

function carryTo(card: Element): void {
    const container = card.querySelector<HTMLElement>("[data-listing-swatches]");
    const target = withoutHash(container?.dataset.productUrl ?? "");
    if (!container || !target) {
        return;
    }
    const hash = selectionHash(card);
    card.querySelectorAll<HTMLAnchorElement>("a[href]").forEach((link) => {
        if (container.contains(link)) {
            return;
        }
        const origin = link.dataset.swatchOrigin ?? link.getAttribute("href") ?? "";
        if (withoutHash(origin) !== target || origin.includes("#")) {
            return;
        }
        link.dataset.swatchOrigin = origin;
        link.setAttribute("href", hash ? `${origin}${hash}` : origin);
    });
}

function showImage(card: Element, chosen: HTMLElement | null): void {
    const image = card.querySelector<HTMLImageElement>(".product-card__media img");
    if (!image) {
        return;
    }
    if (image.dataset.swatchSrc === undefined) {
        image.dataset.swatchSrc = image.getAttribute("src") ?? "";
        image.dataset.swatchSrcset = image.getAttribute("srcset") ?? "";
    }
    const src = chosen?.dataset.imageSrc ?? image.dataset.swatchSrc;
    const srcset = chosen?.dataset.imageSrc ? (chosen.dataset.imageSrcset ?? "") : (image.dataset.swatchSrcset ?? "");
    image.setAttribute("src", src);
    if (srcset) {
        image.setAttribute("srcset", srcset);
    } else {
        image.removeAttribute("srcset");
    }
}

export function choose(swatch: HTMLElement): void {
    const card = swatch.closest(CARD);
    const group = swatch.closest("[data-attribute-code]");
    if (!card || !group) {
        return;
    }
    const wasChosen = swatch.getAttribute(SELECTED) === "true";
    group.querySelectorAll(SWATCH).forEach((other) => other.removeAttribute(SELECTED));
    if (!wasChosen) {
        swatch.setAttribute(SELECTED, "true");
    }
    const withImage = Array.from(card.querySelectorAll<HTMLElement>(`${SWATCH}[${SELECTED}="true"]`)).find(
        (chosen) => chosen.dataset.imageSrc,
    );
    showImage(card, withImage ?? null);
    carryTo(card);
}

export function init(): void {
    document.addEventListener("click", (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        const swatch = (event.target as Element | null)?.closest?.<HTMLElement>(SWATCH);
        if (!swatch || !swatch.closest("[data-listing-swatches]")) {
            return;
        }
        event.preventDefault();
        choose(swatch);
    });
}

init();
