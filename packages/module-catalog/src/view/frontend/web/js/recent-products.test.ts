// This file is part of the MageObsidian - Catalog project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect, beforeEach, vi } from "vitest";
import { fillWidgets } from "./recent-products";
import { readConfig, record } from "./recently";

const NOW = 1_800_000_000_000;

const widget = (kind: string, count = 5) =>
    `<section data-recent-products data-kind="${kind}" data-url="https://shop.test/mageobsidian-catalog/recent/cards" data-count="${count}" hidden>` +
    '<div data-recent-products-list></div></section>';

const CONFIG = '<div hidden data-recently-config data-scope="website-1" data-viewed-lifetime="1000" data-compared-lifetime="1000"></div>';

const remember = (kind: "viewed" | "compared", ...ids: string[]) =>
    ids.forEach((id, index) => record(kind, id, readConfig()!, { now: NOW + index * 1000 }));

const section = () => document.querySelector<HTMLElement>("[data-recent-products]")!;

beforeEach(() => {
    window.localStorage.clear();
    document.body.innerHTML = "";
});

describe("filling the recently viewed and compared widgets", () => {
    it("asks for the remembered ids newest first and shows what comes back", async () => {
        document.body.innerHTML = CONFIG + widget("viewed");
        remember("viewed", "4", "9");
        const fetcher = vi.fn(async () => "<article>Card</article>");

        await fillWidgets({ now: NOW + 5000, fetcher });

        expect(fetcher).toHaveBeenCalledWith("https://shop.test/mageobsidian-catalog/recent/cards?ids=9%2C4&count=5");
        expect(section().hidden).toBe(false);
        expect(section().querySelector("[data-recent-products-list]")!.innerHTML).toBe("<article>Card</article>");
    });

    it("stays hidden for a shopper with no history", async () => {
        document.body.innerHTML = CONFIG + widget("viewed");
        const fetcher = vi.fn(async () => "<article>Card</article>");

        await fillWidgets({ now: NOW, fetcher });

        expect(fetcher).not.toHaveBeenCalled();
        expect(section().hidden).toBe(true);
    });

    it("stays hidden when the server returns nothing to show", async () => {
        document.body.innerHTML = CONFIG + widget("viewed");
        remember("viewed", "4");

        await fillWidgets({ now: NOW + 1000, fetcher: async () => "  " });

        expect(section().hidden).toBe(true);
    });

    it("stays hidden when the request fails", async () => {
        document.body.innerHTML = CONFIG + widget("viewed");
        remember("viewed", "4");

        await fillWidgets({
            now: NOW + 1000,
            fetcher: async () => {
                throw new Error("offline");
            },
        });

        expect(section().hidden).toBe(true);
    });

    it("keeps the two lists apart", async () => {
        document.body.innerHTML = CONFIG + widget("compared");
        remember("viewed", "4");
        remember("compared", "7");
        const fetcher = vi.fn(async () => "<article>Card</article>");

        await fillWidgets({ now: NOW + 1000, fetcher });

        expect(fetcher).toHaveBeenCalledWith(expect.stringContaining("ids=7"));
    });

    it("leaves out the product the page is showing", async () => {
        document.body.innerHTML = CONFIG + widget("viewed") + '<div hidden data-recently-viewed="9"></div>';
        remember("viewed", "4", "9");
        const fetcher = vi.fn(async () => "<article>Card</article>");

        await fillWidgets({ now: NOW + 5000, fetcher });

        expect(fetcher).toHaveBeenCalledWith(expect.stringContaining("ids=4&"));
    });

    it("asks for no more products than the widget is set to show", async () => {
        document.body.innerHTML = CONFIG + widget("viewed", 2);
        remember("viewed", "1", "2", "3");
        const fetcher = vi.fn(async () => "<article>Card</article>");

        await fillWidgets({ now: NOW + 5000, fetcher });

        expect(fetcher).toHaveBeenCalledWith(expect.stringContaining("ids=3%2C2&count=2"));
    });

    it("adds what the server remembers when the store synchronises", async () => {
        document.body.innerHTML =
            '<div hidden data-recently-config data-scope="website-1" data-sync="1" data-viewed-lifetime="1000"></div>' +
            widget("viewed");
        const fetcher = vi.fn(async () => "<article>Card</article>");

        await fillWidgets({
            now: NOW + 5000,
            fetcher,
            remote: () => [{ id: "55", at: NOW + 4000 }],
        });

        expect(fetcher).toHaveBeenCalledWith(expect.stringContaining("ids=55"));
    });

    it("ignores what the server remembers while synchronisation is off", async () => {
        document.body.innerHTML = CONFIG + widget("viewed");
        const fetcher = vi.fn(async () => "<article>Card</article>");

        await fillWidgets({ now: NOW + 5000, fetcher, remote: () => [{ id: "55", at: NOW + 4000 }] });

        expect(fetcher).not.toHaveBeenCalled();
    });

    it("does not ask again for the products it is already showing", async () => {
        document.body.innerHTML = CONFIG + widget("viewed");
        remember("viewed", "4");
        const fetcher = vi.fn(async () => "<article>Card</article>");

        await Promise.all([fillWidgets({ now: NOW + 1000, fetcher }), fillWidgets({ now: NOW + 1000, fetcher })]);
        await fillWidgets({ now: NOW + 1000, fetcher });

        expect(fetcher).toHaveBeenCalledTimes(1);
    });

    it("asks again once the history changes", async () => {
        document.body.innerHTML = CONFIG + widget("viewed");
        remember("viewed", "4");
        const fetcher = vi.fn(async () => "<article>Card</article>");

        await fillWidgets({ now: NOW + 1000, fetcher });
        record("viewed", "9", readConfig()!, { now: NOW + 2000 });
        await fillWidgets({ now: NOW + 3000, fetcher });

        expect(fetcher).toHaveBeenCalledTimes(2);
        expect(fetcher).toHaveBeenLastCalledWith(expect.stringContaining("ids=9%2C4"));
    });

    it("tries again after a request that failed", async () => {
        document.body.innerHTML = CONFIG + widget("viewed");
        remember("viewed", "4");
        const fetcher = vi
            .fn<(url: string) => Promise<string>>()
            .mockRejectedValueOnce(new Error("offline"))
            .mockResolvedValue("<article>Card</article>");

        await fillWidgets({ now: NOW + 1000, fetcher });
        await fillWidgets({ now: NOW + 1000, fetcher });

        expect(fetcher).toHaveBeenCalledTimes(2);
        expect(section().hidden).toBe(false);
    });

    it("hides a widget again once its history has lapsed", async () => {
        document.body.innerHTML = CONFIG + widget("viewed");
        remember("viewed", "4");
        section().hidden = false;

        await fillWidgets({ now: NOW + 2_000_000, fetcher: async () => "<article>Card</article>" });

        expect(section().hidden).toBe(true);
    });
});
