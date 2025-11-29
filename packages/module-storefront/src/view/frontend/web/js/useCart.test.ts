import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import { useCart, getFormKey } from "./useCart.ts";
import { __setSection, __reset, reload } from "MageObsidian_ModernFrontend::js/customer-data";

// useCart reuses Magento's native session quote: POST to checkout/cart/add, then
// reload the cart section so the reactive count updates everywhere.
beforeEach(() => __reset());
afterEach(() => {
    vi.unstubAllGlobals();
    document.body.innerHTML = "";
});

describe("useCart", () => {
    it("reflects the cart section summary_count reactively", () => {
        __setSection("cart", { summary_count: 3 });

        const { count } = useCart();

        expect(count.value).toBe(3);
    });

    it("posts the add-to-cart form and reloads the cart section", async () => {
        const fetchMock = vi.fn().mockResolvedValue({ ok: true });
        vi.stubGlobal("fetch", fetchMock);
        document.body.innerHTML =
            '<form action="/checkout/cart/add" data-add-to-cart>' +
            '<input name="product" value="42"><input name="form_key" value="abc"></form>';
        const form = document.querySelector("form");

        const ok = await useCart().addFromForm(form);

        expect(ok).toBe(true);
        expect(fetchMock).toHaveBeenCalledWith(
            expect.stringContaining("/checkout/cart/add"),
            expect.objectContaining({ method: "POST" }),
        );
        expect(reload.calls.at(-1)).toEqual([["cart"]]);
    });

    it("still reloads the cart when the request fails (so state stays consistent)", async () => {
        vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new Error("network")));
        document.body.innerHTML = '<form action="/checkout/cart/add" data-add-to-cart></form>';

        const ok = await useCart().addFromForm(document.querySelector("form"));

        expect(ok).toBe(false);
        expect(reload.calls.at(-1)).toEqual([["cart"]]);
    });

    it("reads the form key from the cookie as a fallback", () => {
        document.cookie = "form_key=cookiekey";
        expect(getFormKey()).toBe("cookiekey");
    });

    it("adds a configurable product, expanding super_attribute into nested keys", async () => {
        const fetchMock = vi.fn().mockResolvedValue({ ok: true });
        vi.stubGlobal("fetch", fetchMock);
        document.cookie = "form_key=ck";

        const ok = await useCart().addProduct({
            action: "/checkout/cart/add",
            product: 7,
            qty: 2,
            uenc: "ENC",
            superAttribute: { 93: 5, 144: 9 },
        });

        expect(ok).toBe(true);
        const body = fetchMock.mock.calls.at(-1)[1].body;
        expect(body.get("product")).toBe("7");
        expect(body.get("qty")).toBe("2");
        expect(body.get("uenc")).toBe("ENC");
        expect(body.get("super_attribute[93]")).toBe("5");
        expect(body.get("super_attribute[144]")).toBe("9");
        expect(body.get("form_key")).toBe("ck");
        expect(reload.calls.at(-1)).toEqual([["cart"]]);
    });

    it("updates a line item quantity via the sidebar endpoint and reloads the cart", async () => {
        const fetchMock = vi.fn().mockResolvedValue({ ok: true });
        vi.stubGlobal("fetch", fetchMock);
        document.cookie = "form_key=ck";

        const ok = await useCart().updateItemQty(15, 3, "/checkout/sidebar/updateItemQty");

        expect(ok).toBe(true);
        const [action, init] = fetchMock.mock.calls.at(-1);
        expect(action).toBe("/checkout/sidebar/updateItemQty");
        expect(init.method).toBe("POST");
        expect(init.body.get("item_id")).toBe("15");
        expect(init.body.get("item_qty")).toBe("3");
        expect(init.body.get("form_key")).toBe("ck");
        expect(reload.calls.at(-1)).toEqual([["cart"]]);
    });

    it("removes a line item via the sidebar endpoint and reloads the cart", async () => {
        const fetchMock = vi.fn().mockResolvedValue({ ok: true });
        vi.stubGlobal("fetch", fetchMock);
        document.cookie = "form_key=ck";

        const ok = await useCart().removeItem(15, "/checkout/sidebar/removeItem");

        expect(ok).toBe(true);
        const [action, init] = fetchMock.mock.calls.at(-1);
        expect(action).toBe("/checkout/sidebar/removeItem");
        expect(init.body.get("item_id")).toBe("15");
        expect(reload.calls.at(-1)).toEqual([["cart"]]);
    });

    it("still reloads the cart when a sidebar mutation fails", async () => {
        vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new Error("network")));
        document.cookie = "form_key=ck";

        const ok = await useCart().removeItem(9, "/checkout/sidebar/removeItem");

        expect(ok).toBe(false);
        expect(reload.calls.at(-1)).toEqual([["cart"]]);
    });
});
