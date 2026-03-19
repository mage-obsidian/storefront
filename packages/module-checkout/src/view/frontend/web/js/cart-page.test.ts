import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import { __calls, __reset } from "MageObsidian_Storefront::js/useCart";
import { reload, __reset as __resetData } from "MageObsidian_ModernFrontend::js/customer-data";

// The enhancer binds delegated listeners on the document at import time, so it is
// imported once and the DOM/stubs are reset per test (the realistic page setup).
import "./cart-page.js";

const CART_HTML = `
<div data-cart-root data-update-url="/checkout/sidebar/updateItemQty" data-remove-url="/checkout/sidebar/removeItem">
  <form data-cart-form>
    <li data-cart-line>
      <button type="button" data-cart-step="-1" aria-label="dec">-</button>
      <input type="number" data-cart-qty data-item-id="15" value="2">
      <button type="button" data-cart-step="1" aria-label="inc">+</button>
      <button type="submit" data-cart-move data-item-id="15" data-move-url="/wishlist/index/fromcart">Move to wish list</button>
      <button type="submit" data-cart-remove data-item-id="15">Remove</button>
    </li>
    <button type="submit" data-cart-update>Update bag</button>
  </form>
  <form data-cart-coupon action="/checkout/cart/couponPost">
    <input name="coupon_code" value="SAVE10">
    <button type="submit">Apply</button>
  </form>
</div>`;

beforeEach(() => {
    __reset();
    __resetData();
    document.body.innerHTML = CART_HTML;
    // refresh() re-fetches the page; return markup that still has the cart root.
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({
        ok: true,
        text: () => Promise.resolve(CART_HTML),
    }));
});
afterEach(() => {
    vi.unstubAllGlobals();
    document.body.innerHTML = "";
});

describe("cart-page enhancer", () => {
    it("steps quantity up and delegates to useCart with the sidebar update URL", async () => {
        document.querySelector('[data-cart-step="1"]').dispatchEvent(new Event("click", { bubbles: true }));

        await vi.waitFor(() => expect(__calls.length).toBeGreaterThan(0));
        expect(__calls.at(-1)).toEqual({
            type: "updateItemQty",
            itemId: "15",
            qty: 3,
            action: "/checkout/sidebar/updateItemQty",
        });
    });

    it("applies a typed quantity on change", async () => {
        const input = document.querySelector("[data-cart-qty]");
        input.value = "5";
        input.dispatchEvent(new Event("change", { bubbles: true }));

        await vi.waitFor(() => expect(__calls.length).toBeGreaterThan(0));
        expect(__calls.at(-1)).toMatchObject({ type: "updateItemQty", itemId: "15", qty: 5 });
    });

    it("removes a line via useCart with the sidebar remove URL", async () => {
        const button = document.querySelector("[data-cart-remove]");
        button.dispatchEvent(new Event("click", { bubbles: true, cancelable: true }));

        await vi.waitFor(() => expect(__calls.length).toBeGreaterThan(0));
        expect(__calls.at(-1)).toEqual({
            type: "removeItem",
            itemId: "15",
            action: "/checkout/sidebar/removeItem",
        });
    });

    it("moves a line to the wish list via fromcart and reloads cart + wishlist", async () => {
        const button = document.querySelector("[data-cart-move]");
        button.dispatchEvent(new Event("click", { bubbles: true, cancelable: true }));

        await vi.waitFor(() => expect(reload.calls.length).toBeGreaterThan(0));
        const postCall = fetch.mock.calls.find(([url]) => String(url).endsWith("/wishlist/index/fromcart"));
        expect(postCall).toBeDefined();
        expect(postCall[1].method).toBe("POST");
        expect(postCall[1].body.get("item")).toBe("15");
        expect(postCall[1].body.get("form_key")).toBe("test-form-key");
        expect(reload.calls.at(-1)).toEqual([["cart", "wishlist"]]);
    });

    it("submits the coupon form in place and reloads the cart section", async () => {
        const form = document.querySelector("[data-cart-coupon]");
        form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));

        await vi.waitFor(() => expect(reload.calls.length).toBeGreaterThan(0));
        const postCall = fetch.mock.calls.find(([url]) => String(url).endsWith("/checkout/cart/couponPost"));
        expect(postCall).toBeDefined();
        expect(postCall[1].method).toBe("POST");
        expect(reload.calls.at(-1)).toEqual([["cart"]]);
    });
});
