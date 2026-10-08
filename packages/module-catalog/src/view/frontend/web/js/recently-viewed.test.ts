// This file is part of the MageObsidian - Catalog project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect, beforeEach, vi } from "vitest";
import { recordView } from "./recently-viewed";
import { recentIds, readConfig } from "./recently";

const NOW = 1_800_000_000_000;

function page(config: string, product: string): void {
    document.body.innerHTML = `${config}<div hidden data-recently-viewed="${product}"></div>`;
}

const CONFIG = '<div hidden data-recently-config data-scope="website-1" data-sync="0" data-sync-url="/s/"></div>';

beforeEach(() => {
    window.localStorage.clear();
    document.body.innerHTML = "";
});

describe("recording a product page view", () => {
    it("remembers the product the page shows", async () => {
        page(CONFIG, "42");

        await recordView({ now: NOW });

        expect(recentIds("viewed", readConfig()!, { now: NOW })).toEqual(["42"]);
    });

    it("does nothing on a page without the marker", async () => {
        document.body.innerHTML = CONFIG;

        await recordView({ now: NOW });

        expect(recentIds("viewed", readConfig()!, { now: NOW })).toEqual([]);
    });

    it("does nothing when the page carries no configuration", async () => {
        document.body.innerHTML = '<div hidden data-recently-viewed="42"></div>';

        await expect(recordView({ now: NOW })).resolves.toBeUndefined();
        expect(window.localStorage.length).toBe(0);
    });

    it("tells the server too when the store synchronises", async () => {
        page('<div hidden data-recently-config data-scope="website-1" data-sync="1" data-sync-url="/s/"></div>', "42");
        const request = vi.fn(async () => ({ ok: true }) as Response);

        await recordView({ now: NOW, request, formKey: "KEY" });

        expect(request).toHaveBeenCalledTimes(1);
    });
});
