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
    successUrl: "https://shop.test/checkout/onepage/success/",
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

    it("defaults the layout to stepped and reads 'onepage' from config", () => {
        const stepped = useCheckout();
        stepped.init(GUEST_CONFIG);
        expect(stepped.layout).toBe("stepped");

        setActivePinia(createPinia());
        const onepage = useCheckout();
        onepage.init({ ...GUEST_CONFIG, layoutMode: "onepage" });
        expect(onepage.layout).toBe("onepage");
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
        checkout.init({ ...GUEST_CONFIG, guestCheckoutLogin: true });

        const available = await checkout.checkEmailAvailable("new@shop.test");

        expect(available).toBe(true);
        expect(fetchMock.mock.calls[0][0]).toBe("https://shop.test/rest/default/V1/customers/isEmailAvailable");
    });

    it("reports an email as taken when an account exists", async () => {
        mockFetch(false);
        const checkout = useCheckout();
        checkout.init({ ...GUEST_CONFIG, guestCheckoutLogin: true });

        expect(await checkout.checkEmailAvailable("ada@shop.test")).toBe(false);
    });

    it("skips the availability check when guest-checkout-login is disabled (the native default)", async () => {
        const fetchMock = mockFetch(false);
        const checkout = useCheckout();
        checkout.init(GUEST_CONFIG);

        expect(await checkout.checkEmailAvailable("ada@shop.test")).toBe(true);
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it("does not block the guest when the availability check errors", async () => {
        mockFetch({ message: "boom" }, false, 500);
        const checkout = useCheckout();
        checkout.init({ ...GUEST_CONFIG, guestCheckoutLogin: true });

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

describe("useCheckout — payment, coupon and order", () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.restoreAllMocks();
    });

    function ready() {
        const checkout = useCheckout();
        checkout.init(GUEST_CONFIG);
        Object.assign(checkout.shippingAddress, {
            firstname: "Ada",
            lastname: "Lovelace",
            street: ["1 Analytical Way", ""],
            city: "Los Angeles",
            region: "California",
            regionId: 12,
            postcode: "90001",
            countryId: "US",
            telephone: "5550100",
        });
        return checkout;
    }

    it("applies a coupon and refreshes the totals breakdown", async () => {
        mockFetch({ grand_total: 54, total_segments: [{ code: "discount", title: "Discount", value: -10 }, { code: "grand_total", title: "Grand Total", value: 54 }] });
        const checkout = ready();

        const ok = await checkout.applyCoupon("SAVE10");

        expect(ok).toBe(true);
        expect(checkout.appliedCoupon).toBe("SAVE10");
        expect(checkout.totalSegments).toHaveLength(2);
        expect(checkout.grandTotal).toBe("$54.00");
    });

    it("surfaces a rejected coupon without applying it", async () => {
        mockFetch({ message: "Code is not valid" }, false, 404);
        const checkout = ready();

        const ok = await checkout.applyCoupon("BAD");

        expect(ok).toBe(false);
        expect(checkout.appliedCoupon).toBe("");
        expect(checkout.couponError).toBe("Code is not valid");
    });

    it("places the order with the shipping address as billing and redirects to success", async () => {
        const assign = vi.fn();
        Object.defineProperty(window, "location", { value: { assign }, writable: true });
        const fetchMock = mockFetch(424242);
        const checkout = ready();
        checkout.selectPayment("checkmo");

        const orderId = await checkout.placeOrder();

        expect(orderId).toBe(424242);
        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe("https://shop.test/rest/default/V1/guest-carts/mask42/payment-information");
        const body = JSON.parse(init.body);
        expect(body.email).toBe("");
        expect(body.paymentMethod).toEqual({ method: "checkmo" });
        expect(body.billingAddress).toMatchObject({ city: "Los Angeles", region_id: 12, country_id: "US" });
        expect(assign).toHaveBeenCalledWith("https://shop.test/checkout/onepage/success/");
    });

    it("refuses to place an order with no payment method", async () => {
        const fetchMock = mockFetch(1);
        const checkout = ready();

        expect(await checkout.placeOrder()).toBeNull();
        expect(fetchMock).not.toHaveBeenCalled();
    });

    const VAULT_TOKEN = {
        publicHash: "hash-visa",
        methodCode: "braintree_cc_vault",
        last4: "1111",
        type: "VI",
        typeLabel: "Visa",
        expiration: "12/2030",
    };

    it("seeds saved cards from the config and stays empty when none are present", () => {
        expect(useCheckout().vaultTokens).toEqual([]);
        setActivePinia(createPinia());
        const withVault = useCheckout();
        withVault.init({ ...GUEST_CONFIG, vault: [VAULT_TOKEN] });
        expect(withVault.vaultTokens).toEqual([VAULT_TOKEN]);
    });

    it("places the order through the vault method with the token public hash", async () => {
        Object.defineProperty(window, "location", { value: { assign: vi.fn() }, writable: true });
        const fetchMock = mockFetch(990099);
        const checkout = ready();
        checkout.vaultTokens = [VAULT_TOKEN];
        checkout.selectVaultToken("hash-visa");

        const orderId = await checkout.placeOrder();

        expect(orderId).toBe(990099);
        const body = JSON.parse(fetchMock.mock.calls[0][1].body);
        expect(body.paymentMethod).toEqual({
            method: "braintree_cc_vault",
            additional_data: { public_hash: "hash-visa" },
        });
    });

    it("falls back to the plain method when the saved card is cleared", async () => {
        Object.defineProperty(window, "location", { value: { assign: vi.fn() }, writable: true });
        const fetchMock = mockFetch(1);
        const checkout = ready();
        checkout.vaultTokens = [VAULT_TOKEN];
        checkout.selectVaultToken("hash-visa");
        checkout.selectPayment("checkmo");

        await checkout.placeOrder();

        expect(JSON.parse(fetchMock.mock.calls[0][1].body).paymentMethod).toEqual({ method: "checkmo" });
    });

    it("surfaces a place-order failure without redirecting", async () => {
        const assign = vi.fn();
        Object.defineProperty(window, "location", { value: { assign }, writable: true });
        mockFetch({ message: "Transaction declined" }, false, 400);
        const checkout = ready();
        checkout.selectPayment("checkmo");

        expect(await checkout.placeOrder()).toBeNull();
        expect(checkout.orderError).toBe("Transaction declined");
        expect(assign).not.toHaveBeenCalled();
    });
});

describe("useCheckout — native config parity", () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.restoreAllMocks();
    });

    it("seeds the native checkout options from config", () => {
        const checkout = useCheckout();
        checkout.init({
            ...GUEST_CONFIG,
            guestCheckout: false,
            guestCheckoutLogin: true,
            displayBillingOnPayment: false,
            maxSummaryItems: 3,
            agreements: { enabled: true, items: [{ agreementId: 7, mode: 1, content: "", checkboxText: "" }] },
        });

        expect(checkout.guestCheckout).toBe(false);
        expect(checkout.guestCheckoutLogin).toBe(true);
        expect(checkout.displayBillingOnPayment).toBe(false);
        expect(checkout.maxSummaryItems).toBe(3);
        expect(checkout.agreementsEnabled).toBe(true);
        expect(checkout.agreements).toHaveLength(1);
    });

    it("caps the order summary to maxSummaryItems and reports the remainder", () => {
        const checkout = useCheckout();
        checkout.init({
            ...GUEST_CONFIG,
            maxSummaryItems: 2,
            quote: { items: [{ id: 1 }, { id: 2 }, { id: 3 }, { id: 4 }], subtotal: "", grandTotal: "" },
        });

        expect(checkout.visibleItems).toHaveLength(2);
        expect(checkout.hiddenItemCount).toBe(2);
    });

    it("treats only manual agreements as required and tracks acceptance", () => {
        const checkout = useCheckout();
        checkout.init({
            ...GUEST_CONFIG,
            agreements: {
                enabled: true,
                items: [
                    { agreementId: 7, mode: 1, content: "", checkboxText: "" },
                    { agreementId: 8, mode: 0, content: "", checkboxText: "" },
                ],
            },
        });

        expect(checkout.requiredAgreementIds).toEqual([7]);
        expect(checkout.allRequiredAccepted).toBe(false);
        checkout.toggleAgreement(7);
        expect(checkout.allRequiredAccepted).toBe(true);
        checkout.toggleAgreement(7);
        expect(checkout.allRequiredAccepted).toBe(false);
    });

    it("blocks place-order until required agreements are accepted, then sends the ids", async () => {
        Object.defineProperty(window, "location", { value: { assign: vi.fn() }, writable: true });
        const fetchMock = mockFetch(555);
        const checkout = useCheckout();
        checkout.init({
            ...GUEST_CONFIG,
            agreements: { enabled: true, items: [{ agreementId: 7, mode: 1, content: "", checkboxText: "" }] },
        });
        Object.assign(checkout.shippingAddress, {
            firstname: "Ada", lastname: "Lovelace", street: ["1 Analytical Way", ""],
            city: "Los Angeles", region: "California", regionId: 12, postcode: "90001", countryId: "US", telephone: "5550100",
        });
        checkout.selectPayment("checkmo");

        expect(await checkout.placeOrder()).toBeNull();
        expect(fetchMock).not.toHaveBeenCalled();

        checkout.toggleAgreement(7);
        const orderId = await checkout.placeOrder();

        expect(orderId).toBe(555);
        const body = JSON.parse(fetchMock.mock.calls[0][1].body);
        expect(body.paymentMethod.extension_attributes.agreement_ids).toEqual(["7"]);
    });
});
