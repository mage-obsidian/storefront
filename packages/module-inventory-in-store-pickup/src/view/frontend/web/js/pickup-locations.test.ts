// This file is part of the MageObsidian - InventoryInStorePickup project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect, vi } from "vitest";
import {
    buildSearchRequest,
    destinationFor,
    findPickupLocations,
    searchTermFor,
    type PickupLocation,
    type PickupSettings,
} from "./pickup-locations.ts";

const SETTINGS: PickupSettings = {
    restBaseUrl: "https://shop.test/rest/default/V1/",
    scopeCode: "base",
    delimiter: ":",
    radius: 200,
    pageSize: 10,
    defaultCountry: "US",
};

const AUSTIN: PickupLocation = {
    pickup_location_code: "austin",
    name: "Austin Studio",
    street: "144 Obsidian Row",
    city: "Austin",
    region: "Texas",
    region_id: 57,
    postcode: "78701",
    country_id: "US",
    phone: "5125550143",
    latitude: 30.27,
    longitude: -97.74,
};

describe("searchTermFor", () => {
    it("joins the query and the country with the delimiter the store uses", () => {
        expect(searchTermFor("78701", "US", ":")).toBe("78701:US");
        expect(searchTermFor("  Houston ", "US", "|")).toBe("Houston|US");
    });

    it("is empty when there is nothing to search for", () => {
        expect(searchTermFor("   ", "US", ":")).toBe("");
    });
});

describe("buildSearchRequest", () => {
    it("asks for the locations that can serve every product in the cart", () => {
        const request = buildSearchRequest(SETTINGS, "78701", "US", ["24-MB01", "24-WB03"]);

        expect(request.searchRequest.scopeCode).toBe("base");
        expect(request.searchRequest.pageSize).toBe(10);
        expect(request.searchRequest.extensionAttributes.productsInfo).toEqual([
            { sku: "24-MB01" },
            { sku: "24-WB03" },
        ]);
        expect(request.searchRequest.area).toEqual({ radius: 200, searchTerm: "78701:US" });
    });

    it("leaves the area out when nothing was typed", () => {
        const request = buildSearchRequest(SETTINGS, "", "US", ["24-MB01"]);

        expect(request.searchRequest.area).toBeUndefined();
    });

    it("falls back to the store's default country", () => {
        expect(buildSearchRequest(SETTINGS, "78701", "", []).searchRequest.area?.searchTerm).toBe("78701:US");
    });
});

describe("findPickupLocations", () => {
    function fetching(payload: unknown, ok = true) {
        return vi.fn().mockResolvedValue({ ok, status: ok ? 200 : 500, json: () => Promise.resolve(payload) });
    }

    it("posts the request to the core endpoint and returns the items", async () => {
        const fetcher = fetching({ items: [AUSTIN], total_count: 1 });

        const found = await findPickupLocations(SETTINGS, "78701", "US", ["24-MB01"], fetcher);

        expect(found).toEqual([AUSTIN]);
        const [url, init] = fetcher.mock.calls[0];
        expect(url).toBe("https://shop.test/rest/default/V1/inventory/in-store-pickup/pickup-locations/");
        expect(init.method).toBe("POST");
        expect(JSON.parse(init.body).searchRequest.area.searchTerm).toBe("78701:US");
    });

    it("returns an empty list when the endpoint has nothing", async () => {
        expect(await findPickupLocations(SETTINGS, "00000", "US", [], fetching({ items: [] }))).toEqual([]);
        expect(await findPickupLocations(SETTINGS, "00000", "US", [], fetching({}))).toEqual([]);
    });

    it("throws when the endpoint answers with an error", async () => {
        await expect(findPickupLocations(SETTINGS, "78701", "US", [], fetching({ message: "no" }, false))).rejects.toThrow();
    });
});

describe("destinationFor", () => {
    it("turns a location into the shipping address that carries its code", () => {
        const destination = destinationFor("instore", AUSTIN, 2);

        expect(destination.carrierCode).toBe("instore");
        expect(destination.extensionAttributes).toEqual({ pickup_location_code: "austin" });
        expect(destination.address).toMatchObject({
            firstname: "Austin Studio",
            lastname: "Store",
            street: ["144 Obsidian Row", ""],
            city: "Austin",
            region: "Texas",
            regionId: 57,
            postcode: "78701",
            countryId: "US",
            telephone: "5125550143",
        });
    });
});
