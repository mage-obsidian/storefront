// This file is part of the MageObsidian - Checkout project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect, beforeEach, vi } from "vitest";
import { mount } from "@vue/test-utils";
import ShippingEstimator from "./ShippingEstimator.vue";

const CONFIG = { isLoggedIn: true, restBaseUrl: "https://shop.test/rest/default/V1/", maskedCartId: "" };

const DIRECTORY = {
    countries: [
        { value: "US", label: "United States" },
        { value: "PT", label: "Portugal" },
    ],
    regions: { US: [{ id: 57, code: "TX", name: "Texas" }] },
    statesRequired: ["US"],
    displayAllRegions: false,
    defaultCountry: "US",
};

const FLATRATE = { carrier_code: "flatrate", method_code: "flatrate", carrier_title: "Flat Rate", method_title: "Fixed", amount: 5, available: true };

function mockFetch(response: unknown, ok = true, status = 200) {
    const fetchMock = vi.fn().mockResolvedValue({ ok, status, json: () => Promise.resolve(response) });
    globalThis.fetch = fetchMock;
    return fetchMock;
}

const flush = () => new Promise((r) => setTimeout(r));

function factory() {
    return mount(ShippingEstimator, {
        props: { config: CONFIG, directory: DIRECTORY, currencyFormat: "$%s" },
    });
}

describe("ShippingEstimator.vue", () => {
    beforeEach(() => {
        vi.restoreAllMocks();
    });

    it("seeds the country with the directory default and shows a region select for it", () => {
        const wrapper = factory();
        const country = wrapper.get("#estimator-country").element as HTMLSelectElement;
        expect(country.value).toBe("US");
        // US has regions → a <select>, not a free-text input.
        expect(wrapper.find("select#estimator-region").exists()).toBe(true);
    });

    it("falls back to a free-text region for a country with no regions", async () => {
        const wrapper = factory();
        await wrapper.get("#estimator-country").setValue("PT");
        expect(wrapper.find("select#estimator-region").exists()).toBe(false);
        expect(wrapper.find("input#estimator-region").exists()).toBe(true);
    });

    it("estimates rates and renders them as a radio group", async () => {
        const fetchMock = mockFetch([FLATRATE]);
        const wrapper = factory();

        await wrapper.get("#estimator-postcode").setValue("78701");
        await wrapper.get("[data-shipping-estimator] button").trigger("click");
        await flush();

        expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({
            address: { country_id: "US", postcode: "78701" },
        });
        await wrapper.vm.$nextTick();
        expect(wrapper.text()).toContain("Flat Rate");
        expect(wrapper.findAll('input[name="estimator-method"]')).toHaveLength(1);
    });

    it("previews the total segments after a rate is selected", async () => {
        mockFetch([FLATRATE]);
        const wrapper = factory();
        await wrapper.get("#estimator-postcode").setValue("78701");
        await wrapper.get("[data-shipping-estimator] button").trigger("click");
        await flush();
        await wrapper.vm.$nextTick();

        mockFetch({
            total_segments: [
                { code: "subtotal", title: "Subtotal", value: 38 },
                { code: "tax", title: "Tax", value: 3 },
                { code: "grand_total", title: "Grand Total", value: 46 },
            ],
        });
        await wrapper.get('input[name="estimator-method"]').trigger("change");
        await flush();
        await wrapper.vm.$nextTick();

        const text = wrapper.text();
        expect(text).toContain("Subtotal");
        expect(text).toContain("Grand Total");
        expect(text).toContain("$46.00");
    });

    describe("tax display", () => {
        const TAXED_RATE = { ...FLATRATE, price_excl_tax: 5, price_incl_tax: 5.4 };

        async function estimate(taxDisplay: Record<string, unknown>) {
            mockFetch([TAXED_RATE]);
            const wrapper = mount(ShippingEstimator, {
                props: { config: CONFIG, directory: DIRECTORY, currencyFormat: "$%s", taxDisplay },
            });
            await wrapper.get("#estimator-postcode").setValue("78701");
            await wrapper.get("[data-shipping-estimator] button").trigger("click");
            await flush();
            await wrapper.vm.$nextTick();
            return wrapper;
        }

        const amounts = (wrapper: ReturnType<typeof mount>) =>
            wrapper.findAll("[data-rate-price] [data-basis]").map((node) => [node.attributes("data-basis"), node.text()]);

        it("offers the rate without tax by default", async () => {
            expect(amounts(await estimate({}))).toEqual([["excl", "$5.00"]]);
        });

        it("offers the rate with tax when the store includes it", async () => {
            expect(amounts(await estimate({ shipping: "incl" }))).toEqual([["incl", "$5.40"]]);
        });

        it("offers both amounts when the store asks for both", async () => {
            const wrapper = await estimate({ shipping: "both" });

            expect(amounts(wrapper)).toEqual([
                ["excl", "$5.00"],
                ["incl", "$5.40"],
            ]);
            expect(wrapper.get("[data-rate-price]").text()).toContain("Excl. Tax");
        });

        it("states the previewed totals in the configured basis", async () => {
            const wrapper = await estimate({ subtotal: "both", shipping: "both", fullSummary: true });
            mockFetch({
                subtotal: 32,
                subtotal_incl_tax: 34.56,
                shipping_amount: 5,
                shipping_incl_tax: 5.4,
                total_segments: [
                    { code: "subtotal", title: "Subtotal", value: 34.56 },
                    { code: "shipping", title: "Shipping & Handling (Flat Rate - Fixed)", value: 5 },
                    {
                        code: "tax",
                        title: "Tax",
                        value: 2.96,
                        extension_attributes: {
                            tax_grandtotal_details: [
                                { amount: 2.96, rates: [{ percent: "6", title: "State" }, { percent: "2", title: "County" }] },
                            ],
                        },
                    },
                    { code: "grand_total", title: "Grand Total", value: 39.96 },
                ],
            });
            await wrapper.get('input[name="estimator-method"]').trigger("change");
            await flush();
            await wrapper.vm.$nextTick();

            const rows = wrapper.findAll("[data-total-row]").map((row) => row.attributes("data-total-row"));
            expect(rows).toEqual([
                "subtotal_excl_tax",
                "subtotal_incl_tax",
                "shipping_excl_tax",
                "shipping_incl_tax",
                "tax",
                "tax_detail",
                "tax_detail",
                "grand_total",
            ]);
        });
    });
});
