import { describe, it, expect, beforeEach } from "vitest";
import { setActivePinia, createPinia } from "pinia";
import { useCheckout } from "./useCheckout.js";

const GUEST_CONFIG = {
    isLoggedIn: false,
    customerEmail: "",
    quote: {
        items: [{ id: 1, name: "Joust Duffle Bag", qty: 1, rowTotal: "$34.00" }],
        subtotal: "$34.00",
        grandTotal: "$34.00",
    },
};

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
});
