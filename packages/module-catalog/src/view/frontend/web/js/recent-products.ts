// This file is part of the MageObsidian - Catalog project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { watch } from "vue";
import { useCustomerData } from "MageObsidian_ModernFrontend::js/customer-data";
import { readConfig, recentIds, serverEntries, type Entry, type RecentKind } from "MageObsidian_Catalog::js/recently";

const WIDGET = "[data-recent-products]";
const LIST = "[data-recent-products-list]";
const SECTIONS: Record<RecentKind, string> = {
    viewed: "recently_viewed_product",
    compared: "recently_compared_product",
};

export interface FillOptions {
    now?: number;
    fetcher?: (url: string) => Promise<string>;
    remote?: (kind: RecentKind) => Entry[];
}

const requested = new WeakMap<HTMLElement, string>();

async function defaultFetcher(url: string): Promise<string> {
    const response = await fetch(url, { credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest" } });
    if (!response.ok) {
        throw new Error(`${response.status}`);
    }
    return response.text();
}

function kindOf(widget: HTMLElement): RecentKind | null {
    return widget.dataset.kind === "viewed" || widget.dataset.kind === "compared" ? widget.dataset.kind : null;
}

async function fillOne(widget: HTMLElement, options: FillOptions): Promise<void> {
    const kind = kindOf(widget);
    const config = readConfig();
    const list = widget.querySelector<HTMLElement>(LIST);
    const url = widget.dataset.url ?? "";
    if (!kind || !config || !list || !url) {
        return;
    }
    const count = Math.max(1, Number.parseInt(widget.dataset.count ?? "", 10) || 5);
    const exclude = document.querySelector<HTMLElement>("[data-recently-viewed]")?.dataset.recentlyViewed;
    const ids = recentIds(kind, config, {
        now: options.now,
        exclude,
        remote: config.sync ? options.remote?.(kind) : undefined,
    }).slice(0, count);
    if (ids.length === 0) {
        requested.delete(widget);
        widget.hidden = true;
        return;
    }
    const target = `${url}?${new URLSearchParams({ ids: ids.join(","), count: String(count) }).toString()}`;
    if (requested.get(widget) === target) {
        return;
    }
    requested.set(widget, target);
    try {
        const html = (await (options.fetcher ?? defaultFetcher)(target)).trim();
        list.innerHTML = html;
        widget.hidden = html === "";
    } catch {
        requested.delete(widget);
        widget.hidden = true;
    }
}

export async function fillWidgets(options: FillOptions = {}): Promise<void> {
    const widgets = Array.from(document.querySelectorAll<HTMLElement>(WIDGET));
    await Promise.all(widgets.map((widget) => fillOne(widget, options)));
}

function start(): void {
    if (!document.querySelector(WIDGET)) {
        return;
    }
    const customerData = useCustomerData();
    const remote = (kind: RecentKind): Entry[] =>
        serverEntries(customerData.section(SECTIONS[kind])?.items as Record<string, never> | undefined);
    void fillWidgets({ remote });
    watch(
        () => [customerData.section(SECTIONS.viewed), customerData.section(SECTIONS.compared)],
        () => void fillWidgets({ remote }),
    );
}

start();
