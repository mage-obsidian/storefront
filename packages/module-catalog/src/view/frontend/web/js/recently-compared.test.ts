// This file is part of the MageObsidian - Catalog project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect, beforeEach, vi } from "vitest";
import { onCompareAdded } from "./recently-compared";
import { readConfig, recentIds } from "./recently";

const NOW = 1_800_000_000_000;

function addition(product: string | null, result: boolean) {
    const body = new FormData();
    if (product !== null) {
        body.set("product", product);
    }
    return { operation: "add", action: "/add", body, cancelled: false, result };
}

beforeEach(() => {
    window.localStorage.clear();
    document.body.innerHTML = '<div hidden data-recently-config data-scope="website-1" data-sync="0"></div>';
});

describe("recording a product added to the comparison", () => {
    it("remembers the product once the add went through", async () => {
        await onCompareAdded(addition("17", true), { now: NOW });

        expect(recentIds("compared", readConfig()!, { now: NOW })).toEqual(["17"]);
    });

    it("ignores an add that failed", async () => {
        await onCompareAdded(addition("17", false), { now: NOW });

        expect(recentIds("compared", readConfig()!, { now: NOW })).toEqual([]);
    });

    it("ignores a request that names no product", async () => {
        await onCompareAdded(addition(null, true), { now: NOW });

        expect(recentIds("compared", readConfig()!, { now: NOW })).toEqual([]);
    });

    it("tells the server too when the store synchronises", async () => {
        document.body.innerHTML = '<div hidden data-recently-config data-scope="website-1" data-sync="1" data-sync-url="/s/"></div>';
        const request = vi.fn(async () => ({ ok: true }) as Response);

        await onCompareAdded(addition("17", true), { now: NOW, request, formKey: "K" });

        expect(request).toHaveBeenCalledTimes(1);
    });
});
