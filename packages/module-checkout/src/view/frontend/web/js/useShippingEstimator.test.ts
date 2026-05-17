import { describe, it, expect, vi, beforeEach } from "vitest";
import { useShippingEstimator } from "./useShippingEstimator.ts";

const REST = "https://shop.test/rest/default/V1/";
const CONFIG = { restBaseUrl: REST, isLoggedIn: true } as const;

function mockFetch(response: unknown = {}, ok = true, status = 200) {
    const fetchMock = vi.fn().mockResolvedValue({
        ok,
        status,
        json: () => Promise.resolve(response),
    });
    globalThis.fetch = fetchMock;
    return fetchMock;
}

describe("useShippingEstimator", () => {
    beforeEach(() => {
        vi.restoreAllMocks();
    });

    it("estimates rates and drops unavailable ones", async () => {
        const fetchMock = mockFetch([
            { carrier_code: "flatrate", method_code: "flatrate", amount: 5, available: true },
            { carrier_code: "freeshipping", method_code: "freeshipping", amount: 0, available: false },
        ]);
        const est = useShippingEstimator(CONFIG);

        await est.estimate({ country_id: "US", postcode: "78701" });

        expect(fetchMock.mock.calls[0][0]).toBe(`${REST}carts/mine/estimate-shipping-methods`);
        expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({
            address: { country_id: "US", postcode: "78701" },
        });
        expect(est.methods.value).toHaveLength(1);
        expect(est.methods.value[0].method_code).toBe("flatrate");
        expect(est.loadingRates.value).toBe(false);
    });

    it("previews totals for a picked rate via totals-information", async () => {
        const fetchMock = mockFetch({
            total_segments: [
                { code: "subtotal", title: "Subtotal", value: 38 },
                { code: "shipping", title: "Shipping", value: 5 },
                { code: "tax", title: "Tax", value: 3.14 },
                { code: "grand_total", title: "Grand Total", value: 46.14 },
            ],
        });
        const est = useShippingEstimator(CONFIG);
        const address = { country_id: "US", region_id: 57, postcode: "78701" };

        await est.selectMethod(address, { carrier_code: "flatrate", method_code: "flatrate", amount: 5 });

        expect(fetchMock.mock.calls[0][0]).toBe(`${REST}carts/mine/totals-information`);
        expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({
            addressInformation: {
                address,
                shipping_method_code: "flatrate",
                shipping_carrier_code: "flatrate",
            },
        });
        expect(est.selectedKey.value).toBe("flatrate_flatrate");
        expect(est.segments.value).toHaveLength(4);
        expect(est.segments.value[3].code).toBe("grand_total");
    });

    it("surfaces Magento's error message and clears stale state on a failed estimate", async () => {
        mockFetch({ message: "No quotes available." }, false, 400);
        const est = useShippingEstimator(CONFIG);

        await est.estimate({ country_id: "US" });

        expect(est.error.value).toBe("No quotes available.");
        expect(est.methods.value).toEqual([]);
        expect(est.loadingRates.value).toBe(false);
    });

    it("resets the previous selection and segments when re-estimating", async () => {
        mockFetch({
            total_segments: [{ code: "grand_total", title: "Grand Total", value: 10 }],
        });
        const est = useShippingEstimator(CONFIG);
        await est.selectMethod({ country_id: "US" }, { carrier_code: "flatrate", method_code: "flatrate" });
        expect(est.segments.value).toHaveLength(1);

        mockFetch([{ carrier_code: "flatrate", method_code: "flatrate", amount: 5 }]);
        await est.estimate({ country_id: "US" });

        expect(est.selectedKey.value).toBe("");
        expect(est.segments.value).toEqual([]);
    });
});
