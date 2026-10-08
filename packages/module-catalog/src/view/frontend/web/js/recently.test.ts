// This file is part of the MageObsidian - Catalog project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect, beforeEach, vi } from "vitest";
import { STORAGE_KEYS, readConfig, record, recentIds, serverEntries, syncRecord, type RecentlyConfig } from "./recently";

const config = (overrides: Partial<RecentlyConfig> = {}): RecentlyConfig => ({
    scope: "website-1",
    sync: false,
    syncUrl: "https://shop.test/catalog/product/frontend_action_synchronize/",
    viewed: { lifetime: 1000, typeId: "recently_viewed_product" },
    compared: { lifetime: 500, typeId: "recently_compared_product" },
    ...overrides,
});

const NOW = 1_800_000_000_000;

beforeEach(() => window.localStorage.clear());

describe("recording", () => {
    it("keeps the latest product first", () => {
        record("viewed", "5", config(), { now: NOW });
        record("viewed", "7", config(), { now: NOW + 1000 });

        expect(recentIds("viewed", config(), { now: NOW + 2000 })).toEqual(["7", "5"]);
    });

    it("moves a product seen again to the front instead of listing it twice", () => {
        record("viewed", "5", config(), { now: NOW });
        record("viewed", "7", config(), { now: NOW + 1000 });
        record("viewed", "5", config(), { now: NOW + 2000 });

        expect(recentIds("viewed", config(), { now: NOW + 3000 })).toEqual(["5", "7"]);
    });

    it("forgets the oldest ids past its cap", () => {
        for (let id = 1; id <= 25; id += 1) {
            record("viewed", String(id), config(), { now: NOW + id * 1000 });
        }

        const ids = recentIds("viewed", config({ viewed: { lifetime: 100000, typeId: "x" } }), { now: NOW + 26000 });
        expect(ids).toHaveLength(20);
        expect(ids[0]).toBe("25");
        expect(ids).not.toContain("1");
    });

    it("keeps the viewed and compared lists apart", () => {
        record("viewed", "5", config(), { now: NOW });
        record("compared", "9", config(), { now: NOW });

        expect(recentIds("viewed", config(), { now: NOW })).toEqual(["5"]);
        expect(recentIds("compared", config(), { now: NOW })).toEqual(["9"]);
        expect(window.localStorage.getItem(STORAGE_KEYS.viewed)).toContain("5");
    });

    it("survives storage that refuses to answer", () => {
        const refusing = {
            getItem: () => { throw new Error("denied"); },
            setItem: () => { throw new Error("denied"); },
        } as unknown as Storage;

        expect(() => record("viewed", "5", config(), { now: NOW, storage: refusing })).not.toThrow();
        expect(recentIds("viewed", config(), { now: NOW, storage: refusing })).toEqual([]);
    });

    it("starts over from storage holding something that is not a list", () => {
        window.localStorage.setItem(STORAGE_KEYS.viewed, "{broken");

        record("viewed", "9", config(), { now: NOW });

        expect(recentIds("viewed", config(), { now: NOW })).toEqual(["9"]);
    });
});

describe("reading", () => {
    it("drops products older than the lifetime configured for that list", () => {
        record("viewed", "5", config(), { now: NOW });
        record("compared", "5", config(), { now: NOW });

        expect(recentIds("viewed", config(), { now: NOW + 999_000 })).toEqual(["5"]);
        expect(recentIds("viewed", config(), { now: NOW + 1_001_000 })).toEqual([]);
        expect(recentIds("compared", config(), { now: NOW + 501_000 })).toEqual([]);
    });

    it("offers only what was seen under the same scope", () => {
        record("viewed", "5", config({ scope: "store-1" }), { now: NOW });
        record("viewed", "6", config({ scope: "website-1" }), { now: NOW });

        expect(recentIds("viewed", config({ scope: "website-1" }), { now: NOW })).toEqual(["6"]);
        expect(recentIds("viewed", config({ scope: "store-1" }), { now: NOW })).toEqual(["5"]);
    });

    it("leaves out the product being viewed", () => {
        record("viewed", "5", config(), { now: NOW });
        record("viewed", "7", config(), { now: NOW + 1000 });

        expect(recentIds("viewed", config(), { now: NOW + 2000, exclude: "7" })).toEqual(["5"]);
    });

    it("merges what the server remembers, newest first, without repeats", () => {
        record("viewed", "5", config(), { now: NOW });
        const remote = serverEntries({ "5": { added_at: 1, product_id: "5" }, "8": { added_at: (NOW + 5000) / 1000, product_id: "8" } });

        const ids = recentIds("viewed", config(), { now: NOW + 6000, remote });

        expect(ids).toEqual(["8", "5"]);
    });

    it("treats a lifetime of zero as nothing kept", () => {
        record("viewed", "5", config({ viewed: { lifetime: 0, typeId: "x" } }), { now: NOW });

        expect(recentIds("viewed", config({ viewed: { lifetime: 0, typeId: "x" } }), { now: NOW })).toEqual([]);
    });
});

describe("server entries", () => {
    it("reads the section's items into timestamps in milliseconds", () => {
        const entries = serverEntries({ "3": { added_at: 1_800_000_000, product_id: "3" } });

        expect(entries).toEqual([{ id: "3", at: 1_800_000_000_000 }]);
    });

    it("ignores a section that carries nothing usable", () => {
        expect(serverEntries(undefined)).toEqual([]);
        expect(serverEntries({ "3": {} as never })).toEqual([]);
    });
});

describe("reading the page configuration", () => {
    beforeEach(() => {
        document.body.innerHTML = "";
    });

    it("returns null when the page carries none", () => {
        expect(readConfig()).toBeNull();
    });

    it("reads scope, lifetimes and the synchronisation flag", () => {
        document.body.innerHTML =
            '<div hidden data-recently-config data-scope="store-2" data-sync="1" data-sync-url="/sync/"' +
            ' data-viewed-lifetime="120" data-compared-lifetime="30"></div>';

        expect(readConfig()).toEqual({
            scope: "store-2",
            sync: true,
            syncUrl: "/sync/",
            viewed: { lifetime: 120, typeId: "recently_viewed_product" },
            compared: { lifetime: 30, typeId: "recently_compared_product" },
        });
    });

    it("falls back to the platform defaults for a lifetime it cannot read", () => {
        document.body.innerHTML = '<div hidden data-recently-config data-scope="website-1" data-viewed-lifetime="soon"></div>';

        expect(readConfig()?.viewed.lifetime).toBe(1000);
        expect(readConfig()?.sync).toBe(false);
    });
});

describe("synchronising with the server", () => {
    it("posts the product with its timestamp and list type", async () => {
        const request = vi.fn(async () => ({ ok: true }) as Response);

        await syncRecord("viewed", "42", config({ sync: true }), { now: NOW, formKey: "KEY", request });

        const [url, init] = request.mock.calls[0] as unknown as [string, RequestInit];
        expect(url).toBe("https://shop.test/catalog/product/frontend_action_synchronize/");
        expect(init.method).toBe("POST");
        const body = init.body as FormData;
        expect(body.get("type_id")).toBe("recently_viewed_product");
        expect(body.get("ids[42][product_id]")).toBe("42");
        expect(body.get("ids[42][added_at]")).toBe(String(NOW / 1000));
        expect(body.get("form_key")).toBe("KEY");
    });

    it("sends nothing while synchronisation is off", async () => {
        const request = vi.fn(async () => ({ ok: true }) as Response);

        await syncRecord("viewed", "42", config({ sync: false }), { now: NOW, formKey: "KEY", request });

        expect(request).not.toHaveBeenCalled();
    });

    it("never lets a failed request surface", async () => {
        const request = vi.fn(async () => { throw new Error("offline"); });

        await expect(syncRecord("viewed", "42", config({ sync: true }), { now: NOW, formKey: "K", request })).resolves.toBeUndefined();
    });
});
