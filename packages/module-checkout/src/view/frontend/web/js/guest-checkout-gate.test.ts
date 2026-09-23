import { describe, it, expect, beforeEach } from "vitest";
import { __reset, __setSection } from "MageObsidian_ModernFrontend::js/customer-data";
import { checkoutDestination, encodeReferer, requiresSignIn, signInUrl } from "./guest-checkout-gate.ts";

const CHECKOUT = "https://shop.test/checkout/";
const LOGIN = "https://shop.test/customer/account/login/";

beforeEach(() => {
    __reset();
});

describe("requiresSignIn", () => {
    it("asks a guest to sign in when the cart refuses guest checkout", () => {
        expect(requiresSignIn({ isGuestCheckoutAllowed: false }, {})).toBe(true);
    });

    it("lets a signed-in customer through whatever the cart says", () => {
        expect(requiresSignIn({ isGuestCheckoutAllowed: false }, { firstname: "Ana" })).toBe(false);
    });

    it("lets a guest through when the cart allows guest checkout", () => {
        expect(requiresSignIn({ isGuestCheckoutAllowed: true }, {})).toBe(false);
    });

    it("lets a guest through while the cart section has not loaded", () => {
        expect(requiresSignIn(null, null)).toBe(false);
        expect(requiresSignIn({}, null)).toBe(false);
    });
});

describe("encodeReferer", () => {
    it.each([
        ["https://shop.test/checkout/", "aHR0cHM6Ly9zaG9wLnRlc3QvY2hlY2tvdXQv"],
        ["https://shop.test/c", "aHR0cHM6Ly9zaG9wLnRlc3QvYw~~"],
        ["https://shop.test/??~", "aHR0cHM6Ly9zaG9wLnRlc3QvPz9-"],
        ["https://shop.test/???", "aHR0cHM6Ly9zaG9wLnRlc3QvPz8_"],
    ])("encodes %s the way Magento's URL encoder does", (url, encoded) => {
        expect(encodeReferer(url)).toBe(encoded);
    });
});

describe("signInUrl", () => {
    it("appends the encoded referer as a path parameter", () => {
        expect(signInUrl(LOGIN, CHECKOUT)).toBe(`${LOGIN}referer/aHR0cHM6Ly9zaG9wLnRlc3QvY2hlY2tvdXQv/`);
    });

    it("adds the separator a login URL without a trailing slash lacks", () => {
        expect(signInUrl("https://shop.test/customer/account/login", CHECKOUT)).toBe(
            `${LOGIN}referer/aHR0cHM6Ly9zaG9wLnRlc3QvY2hlY2tvdXQv/`,
        );
    });
});

describe("checkoutDestination", () => {
    it("sends a guest to sign in when the cart refuses guest checkout", () => {
        __setSection("cart", { isGuestCheckoutAllowed: false });

        expect(checkoutDestination(CHECKOUT, LOGIN)).toBe(signInUrl(LOGIN, CHECKOUT));
    });

    it("keeps the checkout URL when guest checkout is allowed", () => {
        __setSection("cart", { isGuestCheckoutAllowed: true });

        expect(checkoutDestination(CHECKOUT, LOGIN)).toBe(CHECKOUT);
    });

    it("keeps the checkout URL for a signed-in customer", () => {
        __setSection("cart", { isGuestCheckoutAllowed: false });
        __setSection("customer", { firstname: "Ana" });

        expect(checkoutDestination(CHECKOUT, LOGIN)).toBe(CHECKOUT);
    });

    it("keeps the checkout URL when no sign-in URL was provided", () => {
        __setSection("cart", { isGuestCheckoutAllowed: false });

        expect(checkoutDestination(CHECKOUT, undefined)).toBe(CHECKOUT);
    });
});
