import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import { setActivePinia, createPinia } from "pinia";
import { nextTick } from "vue";
import { useCheckout, CheckoutStep, SHIPPING_SYNC_DEBOUNCE_MS } from "./useCheckout.ts";
import { CheckoutOperation } from "./checkout-events.ts";
import events, { dispatched, __reset as __resetEvents } from "MageObsidian_ModernFrontend::js/events";

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

describe("checkout events", () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        __resetEvents();
    });

    it("announces the step change with where it came from", () => {
        const checkout = useCheckout();
        checkout.init(GUEST_CONFIG);

        checkout.goToStep(CheckoutStep.Shipping);

        expect(dispatched.map((d) => d.event)).toEqual(["checkout_step_change"]);
        expect(dispatched[0].data).toEqual({
            from: CheckoutStep.Identification,
            to: CheckoutStep.Shipping,
        });
    });

    it("says nothing when the step does not actually move", () => {
        const checkout = useCheckout();
        checkout.init(GUEST_CONFIG);

        checkout.goToStep(CheckoutStep.Identification);
        checkout.goToStep("nowhere");

        expect(dispatched).toHaveLength(0);
    });

    it("brackets a rate estimate with before and after", async () => {
        mockFetch([FLATRATE]);
        const checkout = useCheckout();
        checkout.init(GUEST_CONFIG);

        await checkout.estimateShipping();

        expect(dispatched.map((d) => d.event)).toEqual([
            "checkout_estimate_shipping_before",
            "checkout_estimate_shipping_after",
        ]);
        expect(dispatched[0].data.operation).toBe(CheckoutOperation.EstimateShipping);
    });

    it("adds a failed phase when the estimate is rejected", async () => {
        mockFetch({ message: "no" }, false, 400);
        const checkout = useCheckout();
        checkout.init(GUEST_CONFIG);

        await checkout.estimateShipping();

        expect(dispatched.map((d) => d.event)).toEqual([
            "checkout_estimate_shipping_before",
            "checkout_estimate_shipping_after",
            "checkout_estimate_shipping_failed",
        ]);
    });

    it("lets a before observer cancel without touching the network", async () => {
        const fetchMock = mockFetch([FLATRATE]);
        const checkout = useCheckout();
        checkout.init(GUEST_CONFIG);
        events.observe("checkout_estimate_shipping_before", (data) => {
            data.cancelled = true;
        });

        await expect(checkout.estimateShipping()).resolves.toBe(false);

        expect(fetchMock).not.toHaveBeenCalled();
        expect(dispatched.map((d) => d.event)).toEqual(["checkout_estimate_shipping_before"]);
    });
});

describe("useCheckout — public/private split (cacheable shell)", () => {
    const PUBLIC_CONFIG = {
        restBaseUrl: "https://shop.test/rest/default/V1/",
        successUrl: "https://shop.test/checkout/onepage/success/",
        layoutMode: "onepage",
        maxSummaryItems: 5,
        defaultCountry: "US",
    };
    const PRIVATE_DATA = {
        isLoggedIn: false,
        customerEmail: "",
        maskedCartId: "mask42",
        currencyFormat: "$%s",
        quote: {
            items: [{ id: 1, name: "Joust Duffle Bag", qty: 1, rowTotal: "$34.00" }],
            subtotal: "$34.00",
            grandTotal: "$34.00",
        },
        vault: [],
    };

    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it("stays unready on the public half alone, so no step can act on an unknown quote", () => {
        const checkout = useCheckout();
        checkout.initPublic(PUBLIC_CONFIG);

        expect(checkout.ready).toBe(false);
        expect(checkout.itemCount).toBe(0);
        expect(checkout.layout).toBe("onepage");
        expect(checkout.maxSummaryItems).toBe(5);
    });

    it("keeps an email a guest typed before the section landed", () => {
        const checkout = useCheckout();
        checkout.initPublic(PUBLIC_CONFIG);
        checkout.email = "early@shop.test";

        checkout.applyPrivate({ ...PRIVATE_DATA, isLoggedIn: false, customerEmail: "" });

        expect(checkout.email).toBe("early@shop.test");
    });

    it("becomes ready and seeds the quote when the private section arrives", () => {
        const checkout = useCheckout();
        checkout.initPublic(PUBLIC_CONFIG);
        checkout.applyPrivate(PRIVATE_DATA);

        expect(checkout.ready).toBe(true);
        expect(checkout.itemCount).toBe(1);
        expect(checkout.grandTotal).toBe("$34.00");
        expect(checkout.currencyFormat).toBe("$%s");
    });

    it("skips identification for a known customer once the private half lands", () => {
        const checkout = useCheckout();
        checkout.initPublic(PUBLIC_CONFIG);
        checkout.applyPrivate({ ...PRIVATE_DATA, isLoggedIn: true, customerEmail: "ada@shop.test" });

        expect(checkout.step).toBe(CheckoutStep.Shipping);
    });

    // The section reloads after every cart mutation, so a second delivery is the
    // norm, not an edge case — and it must not wipe what the shopper has typed.
    it("refreshes the summary on a later section update without discarding typed input", () => {
        const checkout = useCheckout();
        checkout.initPublic(PUBLIC_CONFIG);
        checkout.applyPrivate(PRIVATE_DATA);
        checkout.setEmail("guest@shop.test");

        checkout.applyPrivate({
            ...PRIVATE_DATA,
            quote: { items: [], subtotal: "$0.00", grandTotal: "$0.00" },
        });

        expect(checkout.itemCount).toBe(0);
        expect(checkout.grandTotal).toBe("$0.00");
        expect(checkout.email).toBe("guest@shop.test");
        expect(checkout.step).toBe(CheckoutStep.Shipping);
    });

    it("init still seeds both halves, so an inlined config keeps working", () => {
        const checkout = useCheckout();
        checkout.init({ ...PUBLIC_CONFIG, ...PRIVATE_DATA });

        expect(checkout.ready).toBe(true);
        expect(checkout.itemCount).toBe(1);
        expect(checkout.layout).toBe("onepage");
    });
});

describe("useCheckout — the customer's saved addresses", () => {
    const PUBLIC_CONFIG = {
        restBaseUrl: "https://shop.test/rest/default/V1/",
        layoutMode: "stepped",
        defaultCountry: "US",
    };
    const AUSTIN = {
        id: 20,
        label: "Ada Lovelace, 12 Baker Street, Austin, Texas 78701",
        isDefaultShipping: true,
        firstname: "Ada",
        lastname: "Lovelace",
        company: "Analytical Engines",
        street: ["12 Baker Street", "Flat 3"],
        city: "Austin",
        region: "Texas",
        regionId: 57,
        postcode: "78701",
        countryId: "US",
        telephone: "+1 512 555 0142",
    };
    const MIAMI = {
        ...AUSTIN,
        id: 21,
        label: "Ada Lovelace, 440 Ocean Drive, Miami, Florida 33139",
        isDefaultShipping: false,
        company: "",
        street: ["440 Ocean Drive"],
        city: "Miami",
        region: "Florida",
        regionId: 18,
        postcode: "33139",
        telephone: "+1 305 555 0199",
    };
    const PRIVATE_DATA = {
        isLoggedIn: true,
        customerEmail: "ada@shop.test",
        maskedCartId: "",
        currencyFormat: "$%s",
        quote: { items: [], subtotal: "$0.00", grandTotal: "$0.00" },
        vault: [],
        addresses: [AUSTIN, MIAMI],
    };

    beforeEach(() => {
        setActivePinia(createPinia());
    });

    function seeded() {
        const checkout = useCheckout();
        checkout.initPublic(PUBLIC_CONFIG);
        checkout.applyPrivate(PRIVATE_DATA);

        return checkout;
    }

    it("fills the form from the default shipping address", () => {
        const checkout = seeded();

        expect(checkout.selectedAddressId).toBe(20);
        expect(checkout.shippingAddress.street).toEqual(["12 Baker Street", "Flat 3"]);
        expect(checkout.shippingAddress.city).toBe("Austin");
        expect(checkout.shippingAddress.regionId).toBe(57);
        expect(checkout.shippingAddress.telephone).toBe("+1 512 555 0142");
    });

    it("swaps the whole form when another address is picked", () => {
        const checkout = seeded();
        checkout.selectAddress(21);

        expect(checkout.shippingAddress.city).toBe("Miami");
        expect(checkout.shippingAddress.company).toBe("");
        expect(checkout.shippingAddress.street).toEqual(["440 Ocean Drive", ""]);
    });

    it("does not let edits leak back into the saved address", () => {
        const checkout = seeded();
        checkout.shippingAddress.street[0] = "999 Typo Lane";
        checkout.selectAddress(21);
        checkout.selectAddress(20);

        expect(checkout.shippingAddress.street[0]).toBe("12 Baker Street");
    });

    it("clears the form for a new address", () => {
        const checkout = seeded();
        checkout.selectAddress(null);

        expect(checkout.selectedAddressId).toBeNull();
        expect(checkout.shippingAddress.city).toBe("");
        expect(checkout.shippingAddress.countryId).toBe("US");
    });

    // applyPrivate runs again after every cart mutation.
    it("never overwrites the form on a later section delivery", () => {
        const checkout = seeded();
        checkout.selectAddress(21);
        checkout.applyPrivate({ ...PRIVATE_DATA, quote: { items: [], subtotal: "$9.00", grandTotal: "$9.00" } });

        expect(checkout.shippingAddress.city).toBe("Miami");
        expect(checkout.subtotal).toBe("$9.00");
    });

    it("leaves a guest with an empty form and no picker", () => {
        const checkout = useCheckout();
        checkout.initPublic(PUBLIC_CONFIG);
        checkout.applyPrivate({ ...PRIVATE_DATA, isLoggedIn: false, addresses: [] });

        expect(checkout.savedAddresses).toEqual([]);
        expect(checkout.selectedAddressId).toBeNull();
        expect(checkout.shippingAddress.city).toBe("");
    });

    it("remembers the furthest step reached, so going back does not undo the progress", () => {
        const checkout = useCheckout();
        checkout.init(GUEST_CONFIG);

        checkout.goToStep("shipping");
        checkout.goToStep("payment");
        expect(checkout.furthestStepIndex).toBe(2);

        checkout.goToStep("shipping");
        expect(checkout.stepIndex).toBe(1);
        expect(checkout.furthestStepIndex).toBe(2);
    });

    it("counts the skipped identity step as reached for a known customer", () => {
        const checkout = useCheckout();
        checkout.init({ ...GUEST_CONFIG, isLoggedIn: true, customerEmail: "ada@shop.test" });

        expect(checkout.stepIndex).toBe(1);
        expect(checkout.furthestStepIndex).toBe(1);
    });

    describe("filing the shipping address in the address book", () => {
        const SAVED = {
            id: 20, label: "Ada, 12 Baker Street, Austin", isDefaultShipping: true,
            firstname: "Ada", lastname: "Lovelace", company: "", street: ["12 Baker Street"],
            city: "Austin", region: "Texas", regionId: 57, postcode: "78701",
            countryId: "US", telephone: "5125550142",
        };

        const member = (addresses = []) => {
            const checkout = useCheckout();
            checkout.init({ ...GUEST_CONFIG, isLoggedIn: true, customerEmail: "ada@shop.test", addresses });
            return checkout;
        };

        const sentBody = (fetchMock) => JSON.parse(fetchMock.mock.calls.at(-1)[1].body);

        async function saveWith(checkout) {
            const fetchMock = mockFetch({ payment_methods: [], totals: {} });
            checkout.selectMethod(FLATRATE);
            await checkout.saveShipping();
            return sentBody(fetchMock);
        }

        it("asks Magento to file a new address when the shopper opted in", async () => {
            const checkout = member();
            checkout.saveAddress = true;

            const body = await saveWith(checkout);

            expect(body.addressInformation.shipping_address.save_in_address_book).toBe(1);
        });

        it("sends an explicit no when the shopper opted out", async () => {
            const checkout = member();
            checkout.saveAddress = false;

            const body = await saveWith(checkout);

            expect(body.addressInformation.shipping_address.save_in_address_book).toBe(0);
        });

        it("never files an address that is already in the book", async () => {
            const checkout = member([SAVED]);
            checkout.saveAddress = true;
            expect(checkout.selectedAddressId).toBe(20);

            const body = await saveWith(checkout);

            expect(body.addressInformation.shipping_address).not.toHaveProperty("save_in_address_book");
        });

        it("leaves the flag off the billing copy, which would file the address twice", async () => {
            const checkout = member();
            checkout.saveAddress = true;

            const body = await saveWith(checkout);

            expect(body.addressInformation.billing_address).not.toHaveProperty("save_in_address_book");
        });

        it("says nothing about the address book for a guest", async () => {
            const checkout = useCheckout();
            checkout.init(GUEST_CONFIG);
            checkout.email = "guest@shop.test";
            checkout.saveAddress = true;

            const body = await saveWith(checkout);

            expect(body.addressInformation.shipping_address).not.toHaveProperty("save_in_address_book");
        });
    });
});

describe("useCheckout — keeping the quote and the screen in step", () => {
    const COMPLETE = {
        firstname: "Ada", lastname: "Lovelace", company: "", street: ["1 Rue", ""],
        city: "Paris", region: "", regionId: null, postcode: "75001", countryId: "FR",
        telephone: "0102030405",
    };
    const SAVE_RESPONSE = { payment_methods: [{ code: "checkmo", title: "Check" }], totals: { grand_total: 39 } };
    const ORDER_ID = 424242;

    beforeEach(() => {
        setActivePinia(createPinia());
        vi.restoreAllMocks();
        vi.useFakeTimers();
        __resetEvents();
        Object.defineProperty(window, "location", { value: { assign: vi.fn() }, writable: true });
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    function mockCheckoutFetch() {
        globalThis.fetch = vi.fn().mockImplementation((url) => {
            const path = String(url);
            const body = path.includes("estimate-shipping-methods")
                ? [FLATRATE]
                : path.includes("shipping-information")
                  ? SAVE_RESPONSE
                  : ORDER_ID;
            return Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve(body) });
        });
        return globalThis.fetch;
    }

    const called = (fetchMock, endpoint) =>
        fetchMock.mock.calls.filter((call) => String(call[0]).includes(endpoint));

    function guest(config = {}) {
        const checkout = useCheckout();
        checkout.init({ ...GUEST_CONFIG, ...config });
        checkout.email = "guest@shop.test";
        return checkout;
    }

    const settle = () => vi.advanceTimersByTimeAsync(SHIPPING_SYNC_DEBOUNCE_MS);

    async function shipped(checkout) {
        checkout.shippingAddress = { ...COMPLETE };
        checkout.scheduleShippingSync();
        await settle();
    }

    it("waits for the shopper to stop typing, and asks nothing for an address that did not change", async () => {
        const fetchMock = mockCheckoutFetch();
        const checkout = guest();

        checkout.shippingAddress = { ...COMPLETE };
        checkout.scheduleShippingSync();
        expect(fetchMock).not.toHaveBeenCalled();

        await settle();
        expect(called(fetchMock, "estimate-shipping-methods")).toHaveLength(1);

        checkout.scheduleShippingSync();
        await settle();
        expect(called(fetchMock, "estimate-shipping-methods")).toHaveLength(1);
    });

    it("does not go to the network while the address cannot be quoted", async () => {
        const fetchMock = mockCheckoutFetch();
        const checkout = guest();

        checkout.shippingAddress = { ...COMPLETE, postcode: "" };
        checkout.scheduleShippingSync();
        await settle();

        expect(fetchMock).not.toHaveBeenCalled();
    });

    it("persists the address once a method is chosen, and again after an edit", async () => {
        const fetchMock = mockCheckoutFetch();
        const checkout = guest();
        await shipped(checkout);
        expect(called(fetchMock, "shipping-information")).toHaveLength(1);
        expect(checkout.shippingDirty).toBe(false);

        checkout.shippingAddress = { ...COMPLETE, city: "Lyon" };
        expect(checkout.shippingDirty).toBe(true);

        checkout.scheduleShippingSync();
        await settle();

        const saves = called(fetchMock, "shipping-information");
        expect(saves).toHaveLength(2);
        expect(JSON.parse(saves[1][1].body).addressInformation.shipping_address.city).toBe("Lyon");
        expect(checkout.shippingDirty).toBe(false);
    });

    it("takes the rates, the method and the payment methods down with an address that can no longer be quoted", async () => {
        mockCheckoutFetch();
        const checkout = guest();
        await shipped(checkout);
        expect(checkout.shippingMethods.length).toBeGreaterThan(0);
        expect(checkout.paymentMethods).toHaveLength(1);

        checkout.selectAddress(null);
        await nextTick();

        expect(checkout.shippingMethods).toEqual([]);
        expect(checkout.selectedMethod).toBeNull();
        expect(checkout.paymentMethods).toEqual([]);
        expect(checkout.ratesRequested).toBe(false);
        expect(checkout.shippingDirty).toBe(true);
    });

    it("saves again when the same address comes back, since the payment methods went with it", async () => {
        const fetchMock = mockCheckoutFetch();
        const checkout = guest();
        await shipped(checkout);
        expect(called(fetchMock, "shipping-information")).toHaveLength(1);

        checkout.selectAddress(null);
        await nextTick();
        await shipped(checkout);

        expect(called(fetchMock, "shipping-information")).toHaveLength(2);
        expect(checkout.paymentMethods).toHaveLength(1);
        expect(checkout.shippingDirty).toBe(false);
    });

    it("settles the debounced write before placing the order, so the order carries the edit", async () => {
        const fetchMock = mockCheckoutFetch();
        const checkout = guest();
        await shipped(checkout);

        checkout.selectPayment("checkmo");
        checkout.shippingAddress = { ...COMPLETE, city: "Lyon" };
        checkout.scheduleShippingSync();

        const order = checkout.placeOrder();
        await vi.runAllTimersAsync();

        expect(await order).toBe(ORDER_ID);
        const saves = called(fetchMock, "shipping-information");
        expect(JSON.parse(saves.at(-1)[1].body).addressInformation.shipping_address.city).toBe("Lyon");
        const urls = fetchMock.mock.calls.map((call) => String(call[0]));
        expect(urls.lastIndexOf(urls.find((url) => url.includes("payment-information")))).toBe(urls.length - 1);
    });

    it("refuses the order outright when the quote cannot be brought up to date", async () => {
        const fetchMock = mockCheckoutFetch();
        const checkout = guest();
        await shipped(checkout);
        checkout.selectPayment("checkmo");

        checkout.selectAddress(null);
        await nextTick();
        const orderId = await checkout.placeOrder();

        expect(orderId).toBeNull();
        expect(called(fetchMock, "payment-information")).toHaveLength(0);
    });
});

describe("useCheckout — restoring the shipping choice the quote already holds", () => {
    const HELD = {
        firstname: "Grace", lastname: "Hopper", company: "", street: ["440 Ocean Drive", "Apt 2"],
        city: "Miami", region: "Florida", regionId: 18, postcode: "33139", countryId: "US",
        telephone: "3055550199",
    };
    const AUSTIN = {
        id: 20, label: "Ada, 12 Baker Street, Austin", isDefaultShipping: true,
        firstname: "Ada", lastname: "Lovelace", company: "", street: ["12 Baker Street"],
        city: "Austin", region: "Texas", regionId: 57, postcode: "78701",
        countryId: "US", telephone: "5125550142",
    };
    const PUBLIC_CONFIG = { restBaseUrl: "https://shop.test/rest/default/V1/", defaultCountry: "US" };

    beforeEach(() => {
        setActivePinia(createPinia());
    });

    function seeded(shipping, extra = {}) {
        const checkout = useCheckout();
        checkout.initPublic(PUBLIC_CONFIG);
        checkout.applyPrivate({
            isLoggedIn: false,
            customerEmail: "",
            maskedCartId: "mask42",
            currencyFormat: "$%s",
            quote: { items: [], subtotal: "$0.00", grandTotal: "$0.00" },
            vault: [],
            addresses: [],
            shipping,
            ...extra,
        });
        return checkout;
    }

    it("puts the shopper's own address back, not a blank form", () => {
        const checkout = seeded({ address: HELD, method: { carrier_code: "tablerate", method_code: "bestway" }, email: "grace@shop.test" });

        expect(checkout.shippingAddress.city).toBe("Miami");
        expect(checkout.shippingAddress.street).toEqual(["440 Ocean Drive", "Apt 2"]);
        expect(checkout.shippingAddress.regionId).toBe(18);
        expect(checkout.selectedMethodKey).toBe("tablerate_bestway");
        expect(checkout.email).toBe("grace@shop.test");
        expect(checkout.step).toBe("shipping");
    });

    it("still owes the quote a write, which is the only way the payment methods come back", () => {
        const checkout = seeded({ address: HELD, method: { carrier_code: "tablerate", method_code: "bestway" }, email: "grace@shop.test" });

        expect(checkout.paymentMethods).toEqual([]);
        expect(checkout.shippingDirty).toBe(false);
    });

    it("keeps the quote's address over the customer's default", () => {
        const checkout = seeded(
            { address: HELD, method: { carrier_code: "", method_code: "" }, email: "" },
            { isLoggedIn: true, customerEmail: "ada@shop.test", addresses: [AUSTIN] },
        );

        expect(checkout.shippingAddress.city).toBe("Miami");
        expect(checkout.selectedAddressId).toBeNull();
    });

    it("recognises the quote's address as one from the book, so the picker stays in charge", () => {
        const checkout = seeded(
            { address: { ...AUSTIN, street: ["12 Baker Street"] }, method: { carrier_code: "", method_code: "" }, email: "" },
            { isLoggedIn: true, customerEmail: "ada@shop.test", addresses: [AUSTIN] },
        );

        expect(checkout.selectedAddressId).toBe(20);
        expect(checkout.shippingAddress.city).toBe("Austin");
    });

    it("falls back to the default address when the quote holds nothing", () => {
        const checkout = seeded(
            { address: null, method: { carrier_code: "", method_code: "" }, email: "" },
            { isLoggedIn: true, customerEmail: "ada@shop.test", addresses: [AUSTIN] },
        );

        expect(checkout.selectedAddressId).toBe(20);
        expect(checkout.shippingAddress.city).toBe("Austin");
    });

    it("survives a section that predates the shipping block", () => {
        const checkout = seeded(undefined, { addresses: [AUSTIN], isLoggedIn: true, customerEmail: "ada@shop.test" });

        expect(checkout.selectedAddressId).toBe(20);
    });
});

describe("useCheckout — naming what the address is still missing", () => {
    const QUOTABLE = { countryId: "US", postcode: "33139", region: "Florida", regionId: 18 };

    beforeEach(() => {
        setActivePinia(createPinia());
    });

    function seeded(overrides = {}) {
        const checkout = useCheckout();
        checkout.init({ ...GUEST_CONFIG, statesRequired: ["US"] });
        Object.assign(checkout.shippingAddress, overrides);
        return checkout;
    }

    it("lists the missing fields in form order while the address is already quotable", () => {
        const checkout = seeded({ ...QUOTABLE, city: "Miami" });

        expect(checkout.rateReady).toBe(true);
        expect(checkout.addressComplete).toBe(false);
        expect(checkout.missingAddressFields).toEqual(["firstname", "lastname", "street0", "telephone"]);
    });

    it("empties once the address is complete, in step with addressComplete", () => {
        const checkout = seeded({
            ...QUOTABLE,
            firstname: "Grace",
            lastname: "Hopper",
            street: ["440 Ocean Drive", ""],
            city: "Miami",
            telephone: "3055550199",
        });

        expect(checkout.missingAddressFields).toEqual([]);
        expect(checkout.addressComplete).toBe(true);
    });

    it("only asks for a region where the country demands one", () => {
        const checkout = seeded({ countryId: "US", postcode: "33139" });
        expect(checkout.missingAddressFields).toContain("region");

        checkout.shippingAddress.countryId = "FR";
        expect(checkout.missingAddressFields).not.toContain("region");
    });
});

const STEPS_SHIPPING = 1;

describe("useCheckout — an address that stops being complete after the payment methods landed", () => {
    const COMPLETE = {
        firstname: "Ada", lastname: "Lovelace", company: "", street: ["1 Rue", ""],
        city: "Paris", region: "", regionId: null, postcode: "75001", countryId: "FR",
        telephone: "0102030405",
    };
    const SAVE_RESPONSE = { payment_methods: [{ code: "checkmo", title: "Check" }], totals: { grand_total: 39 } };

    beforeEach(() => {
        setActivePinia(createPinia());
        vi.restoreAllMocks();
    });

    async function paid() {
        const checkout = useCheckout();
        checkout.init(GUEST_CONFIG);
        checkout.email = "ada@shop.test";
        Object.assign(checkout.shippingAddress, COMPLETE);
        mockFetch([FLATRATE]);
        await checkout.estimateShipping();
        mockFetch(SAVE_RESPONSE);
        await checkout.saveShipping();
        return checkout;
    }

    it("takes the payment methods down when a required field is emptied", async () => {
        const checkout = await paid();
        expect(checkout.paymentMethods).toHaveLength(1);

        checkout.shippingAddress.firstname = "";
        await nextTick();

        expect(checkout.paymentMethods).toEqual([]);
        expect(checkout.selectedPayment).toBe("");
    });

    it("leaves the rates alone — the address is still quotable, only unsaveable", async () => {
        const checkout = await paid();

        checkout.shippingAddress.firstname = "";
        await nextTick();

        expect(checkout.shippingMethods).toHaveLength(1);
        expect(checkout.selectedMethodKey).toBe("flatrate_flatrate");
    });

    it("owes the quote a write again, so filling the field back in brings payment back", async () => {
        const checkout = await paid();

        checkout.shippingAddress.firstname = "";
        await nextTick();
        checkout.shippingAddress.firstname = "Ada";
        await nextTick();

        expect(checkout.shippingDirty).toBe(true);

        mockFetch(SAVE_RESPONSE);
        await checkout.saveShipping();

        expect(checkout.paymentMethods).toHaveLength(1);
    });

    it("gives up the step the shipping no longer supports, so the wizard cannot jump back to payment", async () => {
        const checkout = await paid();
        expect(checkout.furthestStepIndex).toBeGreaterThan(STEPS_SHIPPING);

        checkout.shippingAddress.firstname = "";
        await nextTick();

        expect(checkout.furthestStepIndex).toBe(STEPS_SHIPPING);
    });

    it("does the same when a guest clears the email", async () => {
        const checkout = await paid();

        checkout.email = "";
        await nextTick();

        expect(checkout.paymentMethods).toEqual([]);
    });
});
