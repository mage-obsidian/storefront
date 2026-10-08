// This file is part of the MageObsidian - Catalog project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
export const STORAGE_KEYS = {
    viewed: "mage-obsidian-recently-viewed",
    compared: "mage-obsidian-recently-compared",
} as const;

export type RecentKind = keyof typeof STORAGE_KEYS;

const CAP = 20;
const DEFAULT_LIFETIME = 1000;
const TYPE_IDS: Record<RecentKind, string> = {
    viewed: "recently_viewed_product",
    compared: "recently_compared_product",
};

export interface KindConfig {
    lifetime: number;
    typeId: string;
}

export interface RecentlyConfig {
    scope: string;
    sync: boolean;
    syncUrl: string;
    viewed: KindConfig;
    compared: KindConfig;
}

export interface Entry {
    id: string;
    at: number;
}

interface StoredEntry extends Entry {
    scope: string;
}

export interface RecordOptions {
    now?: number;
    storage?: Storage;
}

export interface ReadOptions extends RecordOptions {
    exclude?: string;
    remote?: Entry[];
}

export interface SyncOptions {
    now?: number;
    formKey: string;
    request?: (url: string, init: RequestInit) => Promise<Response>;
}

interface ServerItem {
    added_at?: number | string;
    product_id?: number | string;
}

function open(storage?: Storage): Storage | null {
    try {
        return storage ?? window.localStorage;
    } catch {
        return null;
    }
}

function load(kind: RecentKind, storage: Storage | null): StoredEntry[] {
    if (!storage) {
        return [];
    }
    try {
        const parsed: unknown = JSON.parse(storage.getItem(STORAGE_KEYS[kind]) ?? "[]");
        if (!Array.isArray(parsed)) {
            return [];
        }
        return parsed.filter(
            (entry): entry is StoredEntry =>
                typeof entry === "object" && entry !== null && typeof entry.id === "string" && typeof entry.at === "number",
        );
    } catch {
        return [];
    }
}

function lifetimeOf(kind: RecentKind, config: RecentlyConfig): number {
    return config[kind].lifetime * 1000;
}

function integer(raw: string | undefined, fallback: number): number {
    const value = Number.parseInt(raw ?? "", 10);
    return Number.isFinite(value) && value >= 0 ? value : fallback;
}

export function readConfig(root: ParentNode = document): RecentlyConfig | null {
    const element = root.querySelector<HTMLElement>("[data-recently-config]");
    if (!element) {
        return null;
    }
    const data = element.dataset;
    return {
        scope: data.scope ?? "",
        sync: data.sync === "1",
        syncUrl: data.syncUrl ?? "",
        viewed: { lifetime: integer(data.viewedLifetime, DEFAULT_LIFETIME), typeId: TYPE_IDS.viewed },
        compared: { lifetime: integer(data.comparedLifetime, DEFAULT_LIFETIME), typeId: TYPE_IDS.compared },
    };
}

export function record(kind: RecentKind, productId: string, config: RecentlyConfig, options: RecordOptions = {}): void {
    const storage = open(options.storage);
    if (!storage) {
        return;
    }
    const now = options.now ?? Date.now();
    const kept = load(kind, storage)
        .filter((entry) => !(entry.id === productId && entry.scope === config.scope))
        .filter((entry) => now - entry.at < lifetimeOf(kind, config));
    const next = [{ id: productId, at: now, scope: config.scope }, ...kept].slice(0, CAP);
    try {
        storage.setItem(STORAGE_KEYS[kind], JSON.stringify(next));
    } catch {
        return;
    }
}

export function serverEntries(items: Record<string, ServerItem> | undefined): Entry[] {
    if (!items || typeof items !== "object") {
        return [];
    }
    return Object.entries(items).flatMap(([key, item]) => {
        const addedAt = Number(item?.added_at);
        if (!Number.isFinite(addedAt) || addedAt <= 0) {
            return [];
        }
        return [{ id: String(item.product_id ?? key), at: Math.round(addedAt * 1000) }];
    });
}

export function recentIds(kind: RecentKind, config: RecentlyConfig, options: ReadOptions = {}): string[] {
    const now = options.now ?? Date.now();
    const lifetime = lifetimeOf(kind, config);
    const local = load(kind, open(options.storage)).filter((entry) => entry.scope === config.scope);
    const newest = new Map<string, number>();
    [...local, ...(options.remote ?? [])].forEach((entry) => {
        if (now - entry.at < lifetime && entry.at > (newest.get(entry.id) ?? 0)) {
            newest.set(entry.id, entry.at);
        }
    });
    return [...newest.entries()]
        .sort((first, second) => second[1] - first[1])
        .map(([id]) => id)
        .filter((id) => id !== options.exclude);
}

export async function syncRecord(
    kind: RecentKind,
    productId: string,
    config: RecentlyConfig,
    options: SyncOptions,
): Promise<void> {
    if (!config.sync || !config.syncUrl) {
        return;
    }
    const body = new FormData();
    body.set("type_id", config[kind].typeId);
    body.set(`ids[${productId}][product_id]`, productId);
    body.set(`ids[${productId}][added_at]`, String(Math.floor((options.now ?? Date.now()) / 1000)));
    body.set("form_key", options.formKey);
    const request = options.request ?? ((url, init) => fetch(url, init));
    try {
        await request(config.syncUrl, { method: "POST", body, credentials: "same-origin", keepalive: true });
    } catch {
        return;
    }
}
