import { describe, it, expect, vi, beforeEach } from "vitest";
import { createInstantPurchase, type InstantPurchaseSection } from "./useInstantPurchase";

function fakeStore(section: InstantPurchaseSection | null) {
    return {
        section: vi.fn(() => section),
        reload: vi.fn(async () => {}),
    };
}

function pdpDocument(): Document {
    document.body.innerHTML = `
        <div data-pdp>
            <form class="pdp__form">
                <input type="hidden" name="product" value="42">
                <input type="hidden" name="form_key" value="abc123">
                <input name="qty" value="2">
            </form>
        </div>`;
    return document;
}

const SECTION: InstantPurchaseSection = {
    available: true,
    paymentToken: { publicHash: "hash-1", summary: "Visa ending 1111" },
    shippingAddress: { id: 7, summary: "Austin, TX" },
    billingAddress: { id: 8, summary: "Dallas, TX" },
    shippingMethod: { carrier: "flatrate", method: "flatrate", summary: "Flat Rate" },
};

describe("createInstantPurchase", () => {
    beforeEach(() => {
        document.body.innerHTML = "";
        vi.restoreAllMocks();
    });

    it("exposes availability from the section", () => {
        expect(createInstantPurchase("/buy", document, fakeStore(SECTION)).available.value).toBe(true);
        expect(createInstantPurchase("/buy", document, fakeStore({ available: false })).available.value).toBe(false);
        expect(createInstantPurchase("/buy", document, fakeStore(null)).available.value).toBe(false);
    });

    it("posts the product form fields plus the instant-purchase params", async () => {
        const fetchMock = vi.fn(async () => new Response(JSON.stringify({ response: "Order placed" }), { status: 200 }));
        vi.stubGlobal("fetch", fetchMock);
        const store = fakeStore(SECTION);

        const result = await createInstantPurchase("/instant/buy", pdpDocument(), store).placeOrder();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect(url).toBe("/instant/buy");
        expect((init.headers as Record<string, string>)["X-Requested-With"]).toBe("XMLHttpRequest");
        const body = init.body as FormData;
        expect(body.get("product")).toBe("42");
        expect(body.get("qty")).toBe("2");
        expect(body.get("form_key")).toBe("abc123");
        expect(body.get("instant_purchase_payment_token")).toBe("hash-1");
        expect(body.get("instant_purchase_shipping_address")).toBe("7");
        expect(body.get("instant_purchase_billing_address")).toBe("8");
        expect(body.get("instant_purchase_carrier")).toBe("flatrate");
        expect(body.get("instant_purchase_shipping")).toBe("flatrate");
        expect(result).toEqual({ ok: true, message: "Order placed" });
        expect(store.reload).toHaveBeenCalledOnce();
    });

    it("reports a failed HTTP response without reloading sections", async () => {
        const fetchMock = vi.fn(async () => new Response("nope", { status: 400 }));
        vi.stubGlobal("fetch", fetchMock);
        const store = fakeStore(SECTION);

        const result = await createInstantPurchase("/buy", pdpDocument(), store).placeOrder();

        expect(result.ok).toBe(false);
        expect(store.reload).not.toHaveBeenCalled();
    });

    it("still sends the instant-purchase params when no pdp form is present", async () => {
        const fetchMock = vi.fn(async () => new Response(JSON.stringify({ response: "ok" }), { status: 200 }));
        vi.stubGlobal("fetch", fetchMock);

        await createInstantPurchase("/buy", document, fakeStore(SECTION)).placeOrder();

        const body = (fetchMock.mock.calls[0] as [string, RequestInit])[1].body as FormData;
        expect(body.get("product")).toBeNull();
        expect(body.get("instant_purchase_payment_token")).toBe("hash-1");
    });
});
