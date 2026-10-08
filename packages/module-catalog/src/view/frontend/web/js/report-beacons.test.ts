// This file is part of the MageObsidian - Catalog project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect, beforeEach, vi } from "vitest";
import { sendAll, whenIdle } from "./report-beacons";

const VIEW_URL = "https://shop.test/reports/report_product/view";
const TERMS_URL = "https://shop.test/catalogsearch/searchTermsLog/save";

function marker(attributes: Record<string, string>): void {
    const element = document.createElement("div");
    element.hidden = true;
    Object.entries(attributes).forEach(([name, value]) => element.setAttribute(name, value));
    document.body.append(element);
}

describe("beacons", () => {
    beforeEach(() => {
        document.body.innerHTML = "";
        window.localStorage.clear();
        document.cookie = "form_key=KEY123; path=/";
        window.history.replaceState({}, "", "/catalogsearch/result/?q=yoga+mat");
    });

    it("posts the product view with the form key", () => {
        marker({ "data-report-beacon": "product-view", "data-url": VIEW_URL, "data-product-id": "42" });
        const beacon = vi.fn(() => true);

        sendAll({ beacon });

        expect(beacon).toHaveBeenCalledTimes(1);
        const [url, body] = beacon.mock.calls[0] as unknown as [string, FormData];
        expect(url).toBe(VIEW_URL);
        expect(body.get("product_id")).toBe("42");
        expect(body.get("form_key")).toBe("KEY123");
    });

    it("falls back to a keep-alive request when the beacon is refused", () => {
        marker({ "data-report-beacon": "product-view", "data-url": VIEW_URL, "data-product-id": "42" });
        const request = vi.fn(() => Promise.resolve(new Response("{}")));

        sendAll({ beacon: () => false, request });

        expect(request).toHaveBeenCalledTimes(1);
        const [url, init] = request.mock.calls[0] as unknown as [string, RequestInit];
        expect(url).toBe(VIEW_URL);
        expect(init.method).toBe("POST");
        expect(init.keepalive).toBe(true);
        expect((init.body as FormData).get("product_id")).toBe("42");
    });

    it("asks for the search term log with the term in the address", () => {
        marker({ "data-report-beacon": "search-term", "data-url": TERMS_URL });
        const request = vi.fn(() => Promise.resolve(new Response("{}")));

        sendAll({ beacon: () => true, request });

        expect(request).toHaveBeenCalledTimes(1);
        const [url, init] = request.mock.calls[0] as unknown as [string, RequestInit];
        expect(url).toBe(`${TERMS_URL}?q=yoga+mat`);
        expect(init.method).toBe("GET");
        expect(init.keepalive).toBe(true);
    });

    it("sends no term log when the address names no term", () => {
        window.history.replaceState({}, "", "/catalogsearch/result/");
        marker({ "data-report-beacon": "search-term", "data-url": TERMS_URL });
        const request = vi.fn(() => Promise.resolve(new Response("{}")));

        sendAll({ beacon: () => true, request });

        expect(request).not.toHaveBeenCalled();
    });

    it("does nothing on a page without a marker", () => {
        const beacon = vi.fn(() => true);
        const request = vi.fn(() => Promise.resolve(new Response("{}")));

        sendAll({ beacon, request });

        expect(beacon).not.toHaveBeenCalled();
        expect(request).not.toHaveBeenCalled();
    });

    it("keeps the page quiet when the request fails", async () => {
        marker({ "data-report-beacon": "search-term", "data-url": TERMS_URL });
        const request = vi.fn(() => Promise.reject(new Error("offline")));

        expect(() => sendAll({ beacon: () => true, request })).not.toThrow();
        await Promise.resolve();
    });

    it("sends each marker once", () => {
        marker({ "data-report-beacon": "product-view", "data-url": VIEW_URL, "data-product-id": "42" });
        const beacon = vi.fn(() => true);

        sendAll({ beacon });
        sendAll({ beacon });

        expect(beacon).toHaveBeenCalledTimes(1);
    });
});

describe("idle scheduling", () => {
    it("waits for the browser to be idle instead of running at once", () => {
        const task = vi.fn();
        const idle = vi.fn();

        whenIdle(task, idle);

        expect(task).not.toHaveBeenCalled();
        expect(idle).toHaveBeenCalledTimes(1);
        (idle.mock.calls[0] as unknown as [() => void])[0]();
        expect(task).toHaveBeenCalledTimes(1);
    });
});
