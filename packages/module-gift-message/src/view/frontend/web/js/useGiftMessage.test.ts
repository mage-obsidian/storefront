import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import { useGiftMessage } from "./useGiftMessage.js";

const BASE = "https://shop.test/rest/default/V1/";

function okFetch() {
    return vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve(true) });
}

beforeEach(() => {
    vi.stubGlobal("fetch", okFetch());
});
afterEach(() => {
    vi.unstubAllGlobals();
});

describe("useGiftMessage", () => {
    it("posts the order message to carts/mine for a logged-in shopper, XHR-flagged", async () => {
        const api = useGiftMessage({ restBaseUrl: BASE, isLoggedIn: true, maskedCartId: "" });

        await api.saveOrderMessage({ sender: "Jean", recipient: "Alex", message: "Hi" });

        const [url, init] = fetch.mock.calls[0];
        expect(url).toBe(`${BASE}carts/mine/gift-message`);
        expect(init.method).toBe("POST");
        expect(init.headers["X-Requested-With"]).toBe("XMLHttpRequest");
        expect(JSON.parse(init.body)).toEqual({ giftMessage: { sender: "Jean", recipient: "Alex", message: "Hi" } });
    });

    it("posts a per-item message to the item-scoped endpoint", async () => {
        const api = useGiftMessage({ restBaseUrl: BASE, isLoggedIn: true, maskedCartId: "" });

        await api.saveItemMessage(42, { sender: "Jean", recipient: "Mom", message: "For you" });

        expect(fetch.mock.calls[0][0]).toBe(`${BASE}carts/mine/gift-message/42`);
    });

    it("uses the guest cart path when not logged in", async () => {
        const api = useGiftMessage({ restBaseUrl: BASE, isLoggedIn: false, maskedCartId: "MASK123" });

        await api.saveOrderMessage({ sender: "", recipient: "", message: "" });

        expect(fetch.mock.calls[0][0]).toBe(`${BASE}guest-carts/MASK123/gift-message`);
    });

    it("throws Magento's message on a non-2xx response", async () => {
        vi.stubGlobal("fetch", vi.fn().mockResolvedValue({
            ok: false,
            status: 400,
            json: () => Promise.resolve({ message: "The %1 is invalid.", parameters: ["gift message"] }),
        }));
        const api = useGiftMessage({ restBaseUrl: BASE, isLoggedIn: true, maskedCartId: "" });

        await expect(api.saveOrderMessage({ sender: "", recipient: "", message: "x" }))
            .rejects.toThrow("The gift message is invalid.");
    });
});
