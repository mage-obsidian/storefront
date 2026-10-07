// This file is part of the MageObsidian - Checkout project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import { __calls, __reset } from "MageObsidian_Storefront::js/useCart";
import { reload, __reset as __resetData, __setSection } from "MageObsidian_ModernFrontend::js/customer-data";
import { signInUrl } from "./guest-checkout-gate.ts";
import events from "MageObsidian_ModernFrontend::js/events";
import { MutationPhase, mutationEvent } from "mage-obsidian/runtime/mutationEvent.ts";

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

    it("keeps the stepper focused across the refresh that replaces it", async () => {
        const plus = document.querySelector('[data-cart-step="1"]');
        plus.focus();
        expect(document.activeElement).toBe(plus);

        plus.dispatchEvent(new Event("click", { bubbles: true }));
        await vi.waitFor(() => expect(plus.isConnected).toBe(false));

        expect(document.activeElement).toBe(document.querySelector('[data-cart-step="1"]'));
        expect(document.activeElement).not.toBe(document.body);
    });

    it("keeps the coupon field focused across a quantity refresh", async () => {
        const coupon = document.querySelector('[name="coupon_code"]');
        coupon.focus();

        document.querySelector('[data-cart-step="1"]').dispatchEvent(new Event("click", { bubbles: true }));
        await vi.waitFor(() => expect(coupon.isConnected).toBe(false));

        expect(document.activeElement).toBe(document.querySelector('[name="coupon_code"]'));
    });

    it("leaves focus alone when it was outside the cart region", async () => {
        const outside = document.createElement("button");
        document.body.appendChild(outside);
        outside.focus();

        const plus = document.querySelector('[data-cart-step="1"]');
        plus.dispatchEvent(new Event("click", { bubbles: true }));
        await vi.waitFor(() => expect(plus.isConnected).toBe(false));

        expect(document.activeElement).toBe(outside);
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

    it("submits the coupon form in place and reloads the cart and the messages sections", async () => {
        const form = document.querySelector("[data-cart-coupon]");
        form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));

        await vi.waitFor(() => expect(reload.calls.length).toBeGreaterThan(0));
        const postCall = fetch.mock.calls.find(([url]) => String(url).endsWith("/checkout/cart/couponPost"));
        expect(postCall).toBeDefined();
        expect(postCall[1].method).toBe("POST");
        expect(reload.calls.at(-1)).toEqual([["cart", "messages"]]);
    });
});

describe("coupon challenge", () => {
    it("asks for a fresh challenge after every attempt, since a token is good for one", async () => {
        const { resets, reset } = await import("MageObsidian_Storefront::js/recaptcha");
        reset();
        const form = document.querySelector("[data-cart-coupon]");
        form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));

        await vi.waitFor(() => expect(resets).toEqual(["coupon_code"]));
    });
});

describe("coupon challenge after a refresh", () => {
    it("mounts the challenge again on the region the refresh brought in", async () => {
        const { remounts, reset } = await import("MageObsidian_Storefront::js/recaptcha");
        reset();
        const plus = document.querySelector('[data-cart-step="1"]');
        plus.dispatchEvent(new Event("click", { bubbles: true }));

        await vi.waitFor(() => expect(plus.isConnected).toBe(false));

        expect(remounts).toEqual([document]);
    });
});

describe("coupon rejection", () => {
    it("announces what the server queued in the messages cookie once the attempt is over", async () => {
        const { announced, cookieMessages, reset } = await import("MageObsidian_Storefront::js/session-messages");
        reset();
        const rejection = { type: "error", text: "Something went wrong with reCAPTCHA." };
        cookieMessages.push(rejection);
        const form = document.querySelector("[data-cart-coupon]");
        form.dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));

        await vi.waitFor(() => expect(announced).toEqual([[rejection]]));
    });
});

describe("checkout button", () => {
    const CHECKOUT = "https://shop.test/checkout/";
    const LOGIN = "https://shop.test/customer/account/login/";

    function clickCheckout(): MouseEvent {
        document.body.insertAdjacentHTML(
            "beforeend",
            `<a data-checkout-link data-sign-in-url="${LOGIN}" href="${CHECKOUT}">Proceed to checkout</a>`,
        );
        const click = new MouseEvent("click", { bubbles: true, cancelable: true });
        document.querySelector("[data-checkout-link]")!.dispatchEvent(click);
        return click;
    }

    it("sends a guest to sign in when the cart refuses guest checkout", () => {
        __setSection("cart", { isGuestCheckoutAllowed: false });
        const assign = vi.spyOn(window.location, "assign").mockImplementation(() => {});

        const click = clickCheckout();

        expect(click.defaultPrevented).toBe(true);
        expect(assign).toHaveBeenCalledWith(signInUrl(LOGIN, CHECKOUT));
        assign.mockRestore();
    });

    it("lets the link navigate when guest checkout is allowed", () => {
        __setSection("cart", { isGuestCheckoutAllowed: true });
        const assign = vi.spyOn(window.location, "assign").mockImplementation(() => {});

        const click = clickCheckout();

        expect(click.defaultPrevented).toBe(false);
        expect(assign).not.toHaveBeenCalled();
        assign.mockRestore();
    });
});

describe("a product added from the page", () => {
    const added = (ok: boolean) =>
        events.dispatch(mutationEvent("cart", "add", MutationPhase.After), { result: { ok } });

    it("refreshes the cart region so the new line and the totals appear", async () => {
        const before = document.querySelector("[data-cart-root]");

        await added(true);

        await vi.waitFor(() => expect(before.isConnected).toBe(false));
        expect(fetch).toHaveBeenCalled();
    });

    it("leaves the page alone when the add failed", async () => {
        const before = document.querySelector("[data-cart-root]");

        await added(false);

        expect(before.isConnected).toBe(true);
        expect(fetch).not.toHaveBeenCalled();
    });
});
