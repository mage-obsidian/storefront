// This file is part of the MageObsidian - Catalog project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { getFormKey } from "MageObsidian_Storefront::js/form-key-provider";

export const RECENTLY_VIEWED_KEY = "mage-obsidian-recently-viewed";

const RECENTLY_VIEWED_CAP = 20;
const MARKER = "[data-report-beacon]";
const IDLE_TIMEOUT_MS = 4000;
const FALLBACK_DELAY_MS = 200;

type Beacon = (url: string, body: FormData) => boolean;
type Request = (url: string, init: RequestInit) => Promise<Response>;
type Idle = (task: () => void) => void;

export interface Transport {
    beacon?: Beacon;
    request?: Request;
}

export function remember(productId: string, storage?: Storage): void {
    try {
        const target = storage ?? window.localStorage;
        let ids: string[] = [];
        try {
            const parsed: unknown = JSON.parse(target.getItem(RECENTLY_VIEWED_KEY) ?? "[]");
            ids = Array.isArray(parsed) ? parsed.map(String) : [];
        } catch {
            ids = [];
        }
        const next = [productId, ...ids.filter((id) => id !== productId)].slice(0, RECENTLY_VIEWED_CAP);
        target.setItem(RECENTLY_VIEWED_KEY, JSON.stringify(next));
    } catch {
        return;
    }
}

function defaultBeacon(url: string, body: FormData): boolean {
    return typeof navigator.sendBeacon === "function" && navigator.sendBeacon(url, body);
}

function defaultRequest(url: string, init: RequestInit): Promise<Response> {
    return fetch(url, init);
}

function quietly(pending: Promise<unknown>): void {
    pending.catch(() => undefined);
}

function sendProductView(marker: HTMLElement, transport: Required<Transport>): void {
    const url = marker.dataset.url ?? "";
    const productId = marker.dataset.productId ?? "";
    if (!url || !productId) {
        return;
    }
    remember(productId);
    const body = new FormData();
    body.set("product_id", productId);
    body.set("form_key", getFormKey());
    if (!transport.beacon(url, body)) {
        quietly(transport.request(url, { method: "POST", body, keepalive: true, credentials: "same-origin" }));
    }
}

function sendSearchTerm(marker: HTMLElement, transport: Required<Transport>): void {
    const url = marker.dataset.url ?? "";
    const term = new URLSearchParams(window.location.search).get("q");
    if (!url || !term) {
        return;
    }
    const target = `${url}?${new URLSearchParams({ q: term }).toString()}`;
    quietly(transport.request(target, { method: "GET", keepalive: true, credentials: "same-origin" }));
}

export function sendAll(transport: Transport = {}): void {
    const complete: Required<Transport> = {
        beacon: transport.beacon ?? defaultBeacon,
        request: transport.request ?? defaultRequest,
    };
    document.querySelectorAll<HTMLElement>(MARKER).forEach((marker) => {
        if (marker.dataset.reportBeaconSent === "true") {
            return;
        }
        marker.dataset.reportBeaconSent = "true";
        try {
            if (marker.dataset.reportBeacon === "product-view") {
                sendProductView(marker, complete);
            } else if (marker.dataset.reportBeacon === "search-term") {
                sendSearchTerm(marker, complete);
            }
        } catch {
            return;
        }
    });
}

function browserIdle(task: () => void): void {
    if (typeof window.requestIdleCallback === "function") {
        window.requestIdleCallback(task, { timeout: IDLE_TIMEOUT_MS });
    } else {
        window.setTimeout(task, FALLBACK_DELAY_MS);
    }
}

export function whenIdle(task: () => void, idle: Idle = browserIdle): void {
    idle(task);
}

whenIdle(() => sendAll());
