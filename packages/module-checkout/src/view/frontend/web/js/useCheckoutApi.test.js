import { describe, it, expect, vi, beforeEach } from "vitest";
import { createCheckoutApi } from "./useCheckoutApi.js";

const REST = "https://shop.test/rest/default/V1/";

function mockFetch(response = {}, ok = true, status = 200) {
    const fetchMock = vi.fn().mockResolvedValue({
        ok,
        status,
        json: () => Promise.resolve(response),
    });
    globalThis.fetch = fetchMock;
    return fetchMock;
}

describe("createCheckoutApi — auth-mode URL resolution", () => {
    beforeEach(() => {
        vi.restoreAllMocks();
    });

    it("routes a guest cart through guest-carts/:maskedId", async () => {
        const fetchMock = mockFetch({ ok: true });
        const api = createCheckoutApi({ restBaseUrl: REST, isLoggedIn: false, maskedCartId: "mask42" });

        await api.setShippingInformation({ shipping_address: { city: "Porto" } });

        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe("https://shop.test/rest/default/V1/guest-carts/mask42/shipping-information");
        expect(init.method).toBe("POST");
        expect(init.credentials).toBe("same-origin");
        expect(JSON.parse(init.body)).toEqual({
            addressInformation: { shipping_address: { city: "Porto" } },
        });
    });

    it("routes a logged-in cart through carts/mine (no masked id)", async () => {
        const fetchMock = mockFetch({});
        const api = createCheckoutApi({ restBaseUrl: REST, isLoggedIn: true, maskedCartId: "" });

        await api.getPaymentMethods();

        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe("https://shop.test/rest/default/V1/carts/mine/payment-methods");
        expect(init.method).toBe("GET");
        expect(init.body).toBeUndefined();
    });

    it("wraps payloads under their REST envelope key", async () => {
        const fetchMock = mockFetch([]);
        const api = createCheckoutApi({ restBaseUrl: REST, isLoggedIn: false, maskedCartId: "m" });

        await api.estimateShippingMethods({ country_id: "PT", postcode: "4000" });
        await api.setTotalsInformation({ address: {}, shipping_method_code: "flatrate" });

        expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({
            address: { country_id: "PT", postcode: "4000" },
        });
        expect(fetchMock.mock.calls[1][0]).toBe(
            "https://shop.test/rest/default/V1/guest-carts/m/totals-information"
        );
        expect(JSON.parse(fetchMock.mock.calls[1][1].body)).toEqual({
            totalsInformation: { address: {}, shipping_method_code: "flatrate" },
        });
    });
});

describe("createCheckoutApi — errors", () => {
    beforeEach(() => {
        vi.restoreAllMocks();
    });

    it("throws Magento's interpolated error message on a non-2xx response", async () => {
        mockFetch({ message: 'The "%1" method is not available.', parameters: ["flatrate"] }, false, 400);
        const api = createCheckoutApi({ restBaseUrl: REST, isLoggedIn: true });

        await expect(api.placeOrder({ paymentMethod: { method: "checkmo" } })).rejects.toThrow(
            'The "flatrate" method is not available.'
        );
    });

    it("falls back to a status message when the body is not JSON", async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: false,
            status: 500,
            json: () => Promise.reject(new Error("not json")),
        });
        globalThis.fetch = fetchMock;
        const api = createCheckoutApi({ restBaseUrl: REST, isLoggedIn: true });

        await expect(api.getPaymentMethods()).rejects.toThrow("Checkout request failed (500)");
    });
});
