// This file is part of the MageObsidian - InventoryInStorePickup project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect, vi, beforeEach } from "vitest";
import { flushPromises, mount } from "@vue/test-utils";
import InStorePickup from "./InStorePickup.vue";

const PICKUP = { carrier_code: "instore", method_code: "pickup", carrier_title: "Pick in Store" };
const AUSTIN = {
    pickup_location_code: "austin", name: "Austin Studio", street: "144 Obsidian Row", city: "Austin",
    region: "Texas", region_id: 57, postcode: "78701", country_id: "US", phone: "5125550143",
};
const HOUSTON = { ...AUSTIN, pickup_location_code: "houston", name: "Houston Depot", city: "Houston", postcode: "77002" };

const CONFIG = {
    restBaseUrl: "https://shop.test/rest/default/V1/",
    scopeCode: "base",
    delimiter: ":",
    radius: 200,
    pageSize: 10,
    defaultCountry: "US",
    labels: { pickup: "Pick up in store", ship: "Ship to my address" },
};

const ADDRESS = { countryId: "US", postcode: "75201" };

function answer(items: unknown[]) {
    globalThis.fetch = vi.fn().mockResolvedValue({ ok: true, status: 200, json: () => Promise.resolve({ items }) });
    return globalThis.fetch as ReturnType<typeof vi.fn>;
}

function render(selected: boolean) {
    return mount(InStorePickup, {
        props: { code: "instore", methods: [PICKUP], selected, config: CONFIG, address: ADDRESS as never, skus: ["24-MB01"] },
    });
}

describe("InStorePickup", () => {
    beforeEach(() => {
        vi.restoreAllMocks();
    });

    it("offers shipping and pickup, with shipping checked until pickup is selected", () => {
        const wrapper = render(false);

        expect(wrapper.find('[data-delivery-method="ship"] input').element.checked).toBe(true);
        expect(wrapper.find('[data-delivery-method="pickup"] input').element.checked).toBe(false);
        expect(wrapper.find("[data-pickup-search]").exists()).toBe(false);
    });

    it("selects the carrier's method when pickup is chosen", async () => {
        const wrapper = render(false);

        await wrapper.find('[data-delivery-method="pickup"] input').setValue(true);

        expect(wrapper.emitted("select")?.[0]).toEqual([PICKUP]);
    });

    it("hands the choice back to the checkout when shipping is chosen again", async () => {
        answer([AUSTIN]);
        const wrapper = render(true);
        await flushPromises();

        await wrapper.find('[data-delivery-method="ship"] input').setValue(true);

        expect(wrapper.emitted("deselect")).toHaveLength(1);
    });

    it("starts the search from the shopper's postal code when pickup is already selected", async () => {
        const fetcher = answer([AUSTIN]);
        const wrapper = render(true);
        await flushPromises();

        const body = JSON.parse(fetcher.mock.calls[0][1].body);
        expect(body.searchRequest.area.searchTerm).toBe("75201:US");
        expect(body.searchRequest.extensionAttributes.productsInfo).toEqual([{ sku: "24-MB01" }]);
        expect(wrapper.find('[data-pickup-location="austin"]').exists()).toBe(true);
    });

    it("searches for what the shopper typed and lists only what comes back", async () => {
        answer([AUSTIN]);
        const wrapper = render(true);
        await flushPromises();

        const fetcher = answer([HOUSTON]);
        await wrapper.find("[data-pickup-search] input").setValue("77002");
        await wrapper.find("[data-pickup-find]").trigger("click");
        await flushPromises();

        expect(JSON.parse(fetcher.mock.calls[0][1].body).searchRequest.area.searchTerm).toBe("77002:US");
        expect(wrapper.find('[data-pickup-location="houston"]').exists()).toBe(true);
        expect(wrapper.find('[data-pickup-location="austin"]').exists()).toBe(false);
    });

    it("names the pickup location it was given as the destination", async () => {
        answer([AUSTIN, HOUSTON]);
        const wrapper = render(true);
        await flushPromises();

        await wrapper.find('[data-pickup-location="houston"] input').setValue(true);

        const [destination] = wrapper.emitted("destination")![0] as [{ carrierCode: string; extensionAttributes: unknown; address: { postcode: string } }];
        expect(destination.carrierCode).toBe("instore");
        expect(destination.extensionAttributes).toEqual({ pickup_location_code: "houston" });
        expect(destination.address.postcode).toBe("77002");
    });

    it("says so when nothing is found", async () => {
        answer([]);
        const wrapper = render(true);
        await flushPromises();

        expect(wrapper.find("[data-pickup-status]").text()).toContain("No pickup locations");
    });

    it("says so when the search fails", async () => {
        globalThis.fetch = vi.fn().mockResolvedValue({ ok: false, status: 500, json: () => Promise.resolve({}) });
        const wrapper = render(true);
        await flushPromises();

        expect(wrapper.find("[data-pickup-status] [role=alert]").exists()).toBe(true);
        expect(wrapper.find("[data-pickup-location]").exists()).toBe(false);
    });
});
