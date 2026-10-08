// This file is part of the MageObsidian - InventoryInStorePickup project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { emptyAddress } from "MageObsidian_Storefront::js/address";
import type { CarrierDestination } from "MageObsidian_Checkout::js/useCheckout";

export interface PickupLocation {
    pickup_location_code: string;
    name: string;
    street?: string;
    city?: string;
    region?: string;
    region_id?: number;
    postcode?: string;
    country_id?: string;
    phone?: string;
    latitude?: number;
    longitude?: number;
}

export interface PickupSettings {
    restBaseUrl: string;
    scopeCode: string;
    delimiter: string;
    radius: number;
    pageSize: number;
    defaultCountry: string;
}

interface SearchRequest {
    searchRequest: {
        scopeCode: string;
        pageSize: number;
        extensionAttributes: { productsInfo: Array<{ sku: string }> };
        area?: { radius: number; searchTerm: string };
    };
}

const ENDPOINT = "inventory/in-store-pickup/pickup-locations/";

export function searchTermFor(query: string, country: string, delimiter: string): string {
    const trimmed = query.trim();
    return trimmed === "" ? "" : `${trimmed}${delimiter}${country}`;
}

export function buildSearchRequest(
    settings: PickupSettings,
    query: string,
    country: string,
    skus: string[],
): SearchRequest {
    const term = searchTermFor(query, country || settings.defaultCountry, settings.delimiter);
    const request: SearchRequest = {
        searchRequest: {
            scopeCode: settings.scopeCode,
            pageSize: settings.pageSize,
            extensionAttributes: { productsInfo: skus.map((sku) => ({ sku })) },
        },
    };
    if (term !== "") {
        request.searchRequest.area = { radius: settings.radius, searchTerm: term };
    }
    return request;
}

export async function findPickupLocations(
    settings: PickupSettings,
    query: string,
    country: string,
    skus: string[],
    fetcher: typeof fetch = fetch,
): Promise<PickupLocation[]> {
    const response = await fetcher(`${settings.restBaseUrl}${ENDPOINT}`, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json", Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
        body: JSON.stringify(buildSearchRequest(settings, query, country, skus)),
    });
    const payload = await response.json().catch(() => null);
    if (!response.ok) {
        throw new Error(`Pickup locations request failed (${response.status})`);
    }
    return Array.isArray(payload?.items) ? (payload.items as PickupLocation[]) : [];
}

export function destinationFor(carrierCode: string, location: PickupLocation, streetLines = 2): CarrierDestination {
    const address = emptyAddress(location.country_id ?? "", streetLines);
    address.firstname = location.name;
    address.lastname = "Store";
    address.street[0] = location.street ?? "";
    address.city = location.city ?? "";
    address.region = location.region ?? "";
    address.regionId = location.region_id ?? null;
    address.postcode = location.postcode ?? "";
    address.telephone = location.phone ?? "";
    return {
        carrierCode,
        address,
        extensionAttributes: { pickup_location_code: location.pickup_location_code },
    };
}
