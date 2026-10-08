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

type Purchase = { attributes: string[]; variants: Record<string, { price: string; id: number }> };

function purchaseOf(container: HTMLElement | null): Purchase | null {
    try {
        return container?.dataset.purchase ? (JSON.parse(container.dataset.purchase) as Purchase) : null;
    } catch {
        return null;
    }
}

function setHidden(element: Element | null, hidden: boolean): void {
    if (element) {
        (element as HTMLElement).hidden = hidden;
    }
}

function showPrice(card: Element, text: string | null): void {
    const target = card.querySelector<HTMLElement>(".product-card__price");
    if (!target) {
        return;
    }
    if (target.dataset.swatchPrice === undefined) {
        target.dataset.swatchPrice = target.innerHTML;
    }
    if (text === null) {
        target.innerHTML = target.dataset.swatchPrice;
    } else {
        target.textContent = text;
    }
}

function updatePurchase(card: Element): void {
    const container = card.querySelector<HTMLElement>("[data-listing-swatches]");
    const purchase = purchaseOf(container);
    const area = card.querySelector<HTMLElement>(".product-card__action");
    const form = card.querySelector<HTMLFormElement>("[data-listing-purchase]");
    const unavailable = card.querySelector("[data-listing-unavailable]");
    if (!purchase || !area || !form || !unavailable) {
        return;
    }
    area.append(form, unavailable);
    const link = area.querySelector(":scope > a[href]");
    const chosen = Object.fromEntries(selection(card));
    const complete = purchase.attributes.every((code) => chosen[code]);
    const variant = complete ? purchase.variants[purchase.attributes.map((code) => chosen[code]).join(",")] : undefined;

    form.querySelectorAll<HTMLInputElement>("input[data-attribute-code]").forEach((field) => {
        field.disabled = !variant;
        field.value = variant ? (chosen[field.dataset.attributeCode ?? ""] ?? "") : "";
    });
    if (variant) {
        form.dataset.variantId = String(variant.id);
    } else {
        delete form.dataset.variantId;
    }
    setHidden(form, !variant);
    setHidden(unavailable, !(complete && !variant));
    setHidden(link, complete);
    showPrice(card, variant ? variant.price : null);
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
    updatePurchase(card);
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
