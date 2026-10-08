// This file is part of the MageObsidian - Catalog project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { formatCurrency } from "MageObsidian_Storefront::js/currency";

const CENTS = 100;

export function total(base: number, prices: number[]): number {
    return Math.round((base + prices.reduce((sum, price) => sum + price, 0)) * CENTS) / CENTS;
}

export function setup(form: HTMLFormElement): void {
    const base = Number(form.dataset.basePrice ?? "0");
    const format = form.dataset.currencyFormat ?? "%s";
    const links = Array.from(form.querySelectorAll<HTMLInputElement>("input[data-link-price]"));
    const selectAll = form.querySelector<HTMLInputElement>("[data-links-select-all]");
    const wrappers = Array.from(document.querySelectorAll<HTMLElement>(".pdp__price [data-price-amount]"));

    const render = (): void => {
        const amount = total(
            base,
            links.filter((link) => link.checked).map((link) => Number(link.dataset.linkPrice ?? "0")),
        );
        wrappers.forEach((wrapper) => {
            wrapper.setAttribute("data-price-amount", String(amount));
            const label = wrapper.querySelector(".price");
            if (label) {
                label.textContent = formatCurrency(format, amount);
            }
        });
        if (selectAll) {
            const chosen = links.filter((link) => link.checked).length;
            selectAll.checked = links.length > 0 && chosen === links.length;
            selectAll.indeterminate = chosen > 0 && chosen < links.length;
        }
    };

    links.forEach((link) => link.addEventListener("change", render));
    selectAll?.addEventListener("change", () => {
        links.forEach((link) => {
            link.checked = selectAll.checked;
        });
        render();
    });
    render();
}

export function init(): void {
    document.querySelectorAll<HTMLFormElement>("[data-downloadable-links]").forEach(setup);
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
} else {
    init();
}
