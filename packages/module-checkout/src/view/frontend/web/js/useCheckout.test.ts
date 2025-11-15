import { describe, it, expect, beforeEach, vi } from "vitest";
import { setActivePinia, createPinia } from "pinia";
import { useCheckout } from "./useCheckout.ts";

const GUEST_CONFIG = {
    isLoggedIn: false,
    customerEmail: "",
    restBaseUrl: "https://shop.test/rest/default/V1/",
    maskedCartId: "mask42",
    defaultCountry: "US",
    currencyFormat: "$%s",
    quote: {
        items: [{ id: 1, name: "Joust Duffle Bag", qty: 1, rowTotal: "$34.00" }],
        subtotal: "$34.00",
        grandTotal: "$34.00",
    },
};

function mockFetch(response: unknown, ok = true, status = 200) {
    const fetchMock = vi.fn().mockResolvedValue({
        ok,
        status,
        json: () => Promise.resolve(response),
    });
    globalThis.fetch = fetchMock;
    return fetchMock;
}

const FLATRATE = { carrier_code: "flatrate", method_code: "flatrate", carrier_title: "Flat Rate", available: true };

describe("useCheckout", () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it("seeds items and totals from the server-primed config", () => {
        const checkout = useCheckout();
        checkout.init(GUEST_CONFIG);

        expect(checkout.itemCount).toBe(1);
        expect(checkout.items[0].name).toBe("Joust Duffle Bag");
        expect(checkout.subtotal).toBe("$34.00");
        expect(checkout.grandTotal).toBe("$34.00");
        expect(checkout.step).toBe("identification");
    });

    it("skips the identity step for a known logged-in customer", () => {
        const checkout = useCheckout();
        checkout.init({ ...GUEST_CONFIG, isLoggedIn: true, customerEmail: "ada@shop.test" });

        expect(checkout.email).toBe("ada@shop.test");
        expect(checkout.step).toBe("shipping");
        expect(checkout.stepIndex).toBe(1);
    });

    it("is idempotent: a second init does not clobber state", () => {
        const checkout = useCheckout();
        checkout.init(GUEST_CONFIG);
        checkout.goToStep("payment");
        checkout.init({ ...GUEST_CONFIG, customerEmail: "late@shop.test" });

        expect(checkout.step).toBe("payment");
        expect(checkout.email).toBe("");
    });

    it("only moves to known steps", () => {
        const checkout = useCheckout();
        checkout.init(GUEST_CONFIG);
        checkout.goToStep("nope");
        expect(checkout.step).toBe("identification");
        checkout.goToStep("review");
        expect(checkout.step).toBe("review");
    });

    it("setEmail records the guest email and advances to shipping", () => {
        const checkout = useCheckout();
        checkout.init(GUEST_CONFIG);
        checkout.setEmail("guest@shop.test");

        expect(checkout.email).toBe("guest@shop.test");
        expect(checkout.step).toBe("shipping");
    });
});

describe("useCheckout — shipping actions", () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.restoreAllMocks();
    });

    it("estimates rates against guest-carts and pre-selects the first available", async () => {
        const fetchMock = mockFetch([FLATRATE, { ...FLATRATE, method_code: "x", available: false }]);
        const checkout = useCheckout();
        checkout.init(GUEST_CONFIG);
        checkout.shippingAddress.countryId = "US";
        checkout.shippingAddress.postcode = "94016";

        const ok = await checkout.estimateShipping();

        expect(ok).toBe(true);
        expect(fetchMock.mock.calls[0][0]).toBe(
            "https://shop.test/rest/default/V1/guest-carts/mask42/estimate-shipping-methods",
        );
        expect(checkout.shippingMethods).toHaveLength(1);
        expect(checkout.selectedMethodKey).toBe("flatrate_flatrate");
    });

    it("surfaces a Magento error and clears the rates on a failed estimate", async () => {
        mockFetch({ message: "No shipping" }, false, 400);
        const checkout = useCheckout();
        checkout.init(GUEST_CONFIG);

        const ok = await checkout.estimateShipping();

        expect(ok).toBe(false);
        expect(checkout.error).toBe("No shipping");
        expect(checkout.shippingMethods).toEqual([]);
    });

    it("saves shipping information, stores payment methods and advances to payment", async () => {
        mockFetch({ payment_methods: [{ code: "checkmo", title: "Check / Money order" }], totals: { grand_total: 39 } });
        const checkout = useCheckout();
        checkout.init(GUEST_CONFIG);
        checkout.selectMethod(FLATRATE);

        const ok = await checkout.saveShipping();

        expect(ok).toBe(true);
        expect(checkout.paymentMethods).toEqual([{ code: "checkmo", title: "Check / Money order" }]);
        expect(checkout.grandTotal).toBe("$39.00");
        expect(checkout.step).toBe("payment");
    });

    it("reports an email as available (no existing account) from the native check", async () => {
        const fetchMock = mockFetch(true);
        const checkout = useCheckout();
        checkout.init(GUEST_CONFIG);

        const available = await checkout.checkEmailAvailable("new@shop.test");

        expect(available).toBe(true);
        expect(fetchMock.mock.calls[0][0]).toBe("https://shop.test/rest/default/V1/customers/isEmailAvailable");
    });

    it("reports an email as taken when an account exists", async () => {
        mockFetch(false);
        const checkout = useCheckout();
        checkout.init(GUEST_CONFIG);

        expect(await checkout.checkEmailAvailable("ada@shop.test")).toBe(false);
    });

    it("does not block the guest when the availability check errors", async () => {
        mockFetch({ message: "boom" }, false, 500);
        const checkout = useCheckout();
        checkout.init(GUEST_CONFIG);

        expect(await checkout.checkEmailAvailable("x@shop.test")).toBe(true);
    });

    it("refuses to save shipping with no method selected", async () => {
        const fetchMock = mockFetch({});
        const checkout = useCheckout();
        checkout.init(GUEST_CONFIG);

        const ok = await checkout.saveShipping();

        expect(ok).toBe(false);
        expect(fetchMock).not.toHaveBeenCalled();
    });
});
