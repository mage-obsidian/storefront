import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import { useCart, getFormKey } from "./useCart.ts";
import { __setSection, __reset, reload } from "MageObsidian_ModernFrontend::js/customer-data";
import events, { dispatched, __reset as __resetEvents } from "MageObsidian_ModernFrontend::js/events";

// useCart reuses Magento's native session quote: POST to checkout/cart/add, then
// reload the cart section so the reactive count updates everywhere.
beforeEach(() => {
    __reset();
    __resetEvents();
    // The provider seeds a form_key on import; clear it so a test that pins the
    // value is not reading a second cookie left over from that.
    document.cookie = "form_key=; max-age=0; path=/";
});
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

        const result = await useCart().addFromForm(form);

        expect(result.ok).toBe(true);
        expect(fetchMock).toHaveBeenCalledWith(
            expect.stringContaining("/checkout/cart/add"),
            expect.objectContaining({ method: "POST" }),
        );
        expect(reload.calls.at(-1)).toEqual([["cart", "messages"]]);
    });

    it("still reloads the cart when the request fails (so state stays consistent)", async () => {
        vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new Error("network")));
        document.body.innerHTML = '<form action="/checkout/cart/add" data-add-to-cart></form>';

        const result = await useCart().addFromForm(document.querySelector("form"));

        expect(result.ok).toBe(false);
        expect(reload.calls.at(-1)).toEqual([["cart", "messages"]]);
    });

    // Magento answers an AJAX add-to-cart through Cart::goBack(), which returns
    // HTTP 200 with a `{backUrl}` JSON even when the add failed — the reason lives
    // only in the message manager. Trusting `response.ok` would announce success
    // on every validation failure (a bad file extension, a missing required
    // option), so the verdict comes from the `messages` section instead.
    it("treats a 200 response carrying an error message as a failure", async () => {
        vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: true }));
        __setSection("messages", {
            messages: [{ type: "error", text: "The file you uploaded has an invalid extension." }],
        });
        document.body.innerHTML = '<form action="/checkout/cart/add" data-add-to-cart></form>';

        const result = await useCart().addFromForm(document.querySelector("form"));

        expect(result.ok).toBe(false);
        expect(result.message).toBe("The file you uploaded has an invalid extension.");
    });

    // Magento escapes its messages for HTML output; the toast renders text, so
    // the entities have to be resolved or the shopper reads "&#039;".
    it("decodes HTML entities in the message", async () => {
        vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: true }));
        __setSection("messages", {
            messages: [{ type: "error", text: "The file &#039;art.txt&#039; has an invalid extension." }],
        });
        document.body.innerHTML = '<form action="/checkout/cart/add" data-add-to-cart></form>';

        const result = await useCart().addFromForm(document.querySelector("form"));

        expect(result.message).toBe("The file 'art.txt' has an invalid extension.");
    });

    it("ignores non-error messages so a success notice stays a success", async () => {
        vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: true }));
        __setSection("messages", { messages: [{ type: "notice", text: "Heads up." }] });
        document.body.innerHTML = '<form action="/checkout/cart/add" data-add-to-cart></form>';

        const result = await useCart().addFromForm(document.querySelector("form"));

        expect(result.ok).toBe(true);
        expect(result.message).toBeUndefined();
    });

    it("reads the form key from the cookie as a fallback", () => {
        document.cookie = "form_key=cookiekey; path=/";
        expect(getFormKey()).toBe("cookiekey");
    });

    it("adds a configurable product, expanding super_attribute into nested keys", async () => {
        const fetchMock = vi.fn().mockResolvedValue({ ok: true });
        vi.stubGlobal("fetch", fetchMock);
        document.cookie = "form_key=ck; path=/";

        const result = await useCart().addProduct({
            action: "/checkout/cart/add",
            product: 7,
            qty: 2,
            uenc: "ENC",
            superAttribute: { 93: 5, 144: 9 },
        });

        expect(result.ok).toBe(true);
        const body = fetchMock.mock.calls.at(-1)[1].body;
        expect(body.get("product")).toBe("7");
        expect(body.get("qty")).toBe("2");
        expect(body.get("uenc")).toBe("ENC");
        expect(body.get("super_attribute[93]")).toBe("5");
        expect(body.get("super_attribute[144]")).toBe("9");
        expect(body.get("form_key")).toBe("ck");
        expect(reload.calls.at(-1)).toEqual([["cart", "messages"]]);
    });

    it("updates a line item quantity via the sidebar endpoint and reloads the cart", async () => {
        const fetchMock = vi.fn().mockResolvedValue({ ok: true });
        vi.stubGlobal("fetch", fetchMock);
        document.cookie = "form_key=ck; path=/";

        const result = await useCart().updateItemQty(15, 3, "/checkout/sidebar/updateItemQty");

        expect(result.ok).toBe(true);
        const [action, init] = fetchMock.mock.calls.at(-1);
        expect(action).toBe("/checkout/sidebar/updateItemQty");
        expect(init.method).toBe("POST");
        expect(init.body.get("item_id")).toBe("15");
        expect(init.body.get("item_qty")).toBe("3");
        expect(init.body.get("form_key")).toBe("ck");
        expect(reload.calls.at(-1)).toEqual([["cart", "messages"]]);
    });

    it("removes a line item via the sidebar endpoint and reloads the cart", async () => {
        const fetchMock = vi.fn().mockResolvedValue({ ok: true });
        vi.stubGlobal("fetch", fetchMock);
        document.cookie = "form_key=ck; path=/";

        const result = await useCart().removeItem(15, "/checkout/sidebar/removeItem");

        expect(result.ok).toBe(true);
        const [action, init] = fetchMock.mock.calls.at(-1);
        expect(action).toBe("/checkout/sidebar/removeItem");
        expect(init.body.get("item_id")).toBe("15");
        expect(reload.calls.at(-1)).toEqual([["cart", "messages"]]);
    });

    it("still reloads the cart when a sidebar mutation fails", async () => {
        vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new Error("network")));
        document.cookie = "form_key=ck; path=/";

        const result = await useCart().removeItem(9, "/checkout/sidebar/removeItem");

        expect(result.ok).toBe(false);
        expect(reload.calls.at(-1)).toEqual([["cart", "messages"]]);
    });
});

// Every cart mutation funnels through post(), so the events are announced there
// rather than at each call site — these lock in that a listener sees the whole
// flow and can change it before it leaves.
describe("cart events", () => {
    function stubOk() {
        vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: true }));
        document.cookie = "form_key=ck; path=/";
    }

    it("announces before and after around a successful add", async () => {
        stubOk();

        await useCart().addProduct({ action: "/checkout/cart/add", product: 42 });

        expect(dispatched.map((d) => d.event)).toEqual(["cart_add_before", "cart_add_after"]);
        expect(dispatched.at(-1).data.result).toEqual({ ok: true });
    });

    it("names the operation for a sidebar mutation", async () => {
        stubOk();

        await useCart().updateItemQty(7, 3, "/checkout/sidebar/updateItemQty");

        expect(dispatched.map((d) => d.event)).toEqual([
            "cart_update_qty_before",
            "cart_update_qty_after",
        ]);
        expect(dispatched[0].data.operation).toBe("update_qty");
    });

    it("adds a failed event when the mutation did not succeed", async () => {
        vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new Error("network")));
        document.cookie = "form_key=ck; path=/";

        await useCart().removeItem(9, "/checkout/sidebar/removeItem");

        expect(dispatched.map((d) => d.event)).toEqual([
            "cart_remove_item_before",
            "cart_remove_item_after",
            "cart_remove_item_failed",
        ]);
    });

    it("lets a before observer rewrite the request", async () => {
        const fetchMock = vi.fn().mockResolvedValue({ ok: true });
        vi.stubGlobal("fetch", fetchMock);
        document.cookie = "form_key=ck; path=/";
        events.observe("cart_add_before", (data) => {
            data.action = "/custom/add";
            data.body.set("qty", "9");
        });

        await useCart().addProduct({ action: "/checkout/cart/add", product: 42 });

        const [action, init] = fetchMock.mock.calls.at(-1);
        expect(action).toBe("/custom/add");
        expect(init.body.get("qty")).toBe("9");
    });

    it("lets a before observer cancel without touching the network", async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal("fetch", fetchMock);
        document.cookie = "form_key=ck; path=/";
        events.observe("cart_add_before", (data) => {
            data.cancelled = true;
            data.message = "Out of stock in your region";
        });

        const result = await useCart().addProduct({ action: "/checkout/cart/add", product: 42 });

        expect(fetchMock).not.toHaveBeenCalled();
        expect(result).toEqual({ ok: false, message: "Out of stock in your region" });
        expect(dispatched.map((d) => d.event)).toEqual(["cart_add_before"]);
    });

    it("carries the form key it backfilled, so an observer sees the real body", async () => {
        stubOk();

        await useCart().addProduct({ action: "/checkout/cart/add", product: 42 });

        expect(dispatched[0].data.body.get("form_key")).toBe("ck");
    });
});

describe("optimistic add", () => {
    // The badge moves right after the `_before` observers ran, one microtask in.
    const settled = () => new Promise((resolve) => setTimeout(resolve, 0));

    it("moves the badge before the server answers", async () => {
        __setSection("cart", { summary_count: 2 });
        let resolveFetch;
        globalThis.fetch = vi.fn(() => new Promise((resolve) => { resolveFetch = resolve; }));

        const cart = useCart();
        const pending = cart.addProduct({ action: "/checkout/cart/add/", product: 7, qty: 3 });
        await settled();

        expect(cart.count.value).toBe(5);

        resolveFetch({ ok: true, status: 200 });
        await pending;
    });

    it("counts one line when the badge counts lines rather than units", async () => {
        window.__MAGE_OBSIDIAN_UX__ = { optimistic: true, summaryCountsQty: false };
        __setSection("cart", { summary_count: 2 });
        let resolveFetch;
        globalThis.fetch = vi.fn(() => new Promise((resolve) => { resolveFetch = resolve; }));

        const cart = useCart();
        const pending = cart.addProduct({ action: "/checkout/cart/add/", product: 7, qty: 3 });
        await settled();

        expect(cart.count.value).toBe(3);

        resolveFetch({ ok: true, status: 200 });
        await pending;
        delete window.__MAGE_OBSIDIAN_UX__;
    });

    it("leaves the badge alone for a quantity change or a removal", async () => {
        __setSection("cart", { summary_count: 4 });
        let resolveFetch;
        globalThis.fetch = vi.fn(() => new Promise((resolve) => { resolveFetch = resolve; }));

        const cart = useCart();
        const pending = cart.removeItem(9, "/checkout/sidebar/removeItem/");
        await settled();

        expect(cart.count.value).toBe(4);

        resolveFetch({ ok: true, status: 200 });
        await pending;
    });

    it("says nothing when a before observer cancelled the add", async () => {
        __setSection("cart", { summary_count: 1 });
        events.observe("cart_add_before", (data) => {
            data.cancelled = true;
        });

        await useCart().addProduct({ action: "/checkout/cart/add/", product: 7, qty: 2 });
        await settled();

        expect(useCart().count.value).toBe(1);
    });
});
