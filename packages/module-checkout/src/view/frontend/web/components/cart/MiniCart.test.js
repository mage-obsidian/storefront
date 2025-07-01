import { describe, it, expect, beforeEach, afterEach } from "vitest";
import { mount } from "@vue/test-utils";
import { nextTick } from "vue";
import MiniCart from "./MiniCart.vue";
import { __setSection, __reset } from "MageObsidian_ModernFrontend::js/customer-data";
import { __calls, __reset as __resetCart } from "MageObsidian_Storefront::js/useCart";

const LABELS = {
    title: "Your bag",
    empty: "Your bag is empty",
    emptyHint: "Browse the collection.",
    viewBag: "View bag",
    checkout: "Checkout",
    subtotal: "Subtotal",
    remove: "Remove",
    quantity: "Quantity",
    decrease: "Decrease quantity",
    increase: "Increase quantity",
    close: "Close",
    open: "Open bag",
    items: "items in your bag",
};

const PROPS = {
    cartUrl: "/checkout/cart",
    checkoutUrl: "/checkout",
    updateUrl: "/checkout/sidebar/updateItemQty",
    removeUrl: "/checkout/sidebar/removeItem",
    labels: LABELS,
};

const ITEM = {
    item_id: 15,
    product_name: "Chaz Hoodie",
    product_url: "/chaz-hoodie.html",
    product_price: "$52.00",
    qty: 2,
    product_image: { src: "/media/chaz.jpg", alt: "Chaz" },
    options: [{ label: "Size", value: "M" }, { label: "Color", value: "Gray" }],
};

function addTrigger() {
    const trigger = document.createElement("a");
    trigger.setAttribute("data-minicart-trigger", "");
    trigger.setAttribute("href", "/checkout/cart");
    document.body.appendChild(trigger);
    return trigger;
}

beforeEach(() => {
    __reset();
    __resetCart();
});
afterEach(() => {
    document.body.innerHTML = "";
});

describe("MiniCart", () => {
    it("wires dialog semantics onto the header trigger and opens on click", async () => {
        const trigger = addTrigger();
        mount(MiniCart, { props: PROPS, attachTo: document.body });

        expect(trigger.getAttribute("aria-haspopup")).toBe("dialog");
        expect(trigger.getAttribute("aria-expanded")).toBe("false");

        trigger.dispatchEvent(new Event("click", { bubbles: true, cancelable: true }));
        await nextTick();

        expect(trigger.getAttribute("aria-expanded")).toBe("true");
        expect(document.querySelector('[role="dialog"]')).not.toBeNull();
    });

    it("renders the empty state when the cart section has no items", async () => {
        const trigger = addTrigger();
        mount(MiniCart, { props: PROPS, attachTo: document.body });
        trigger.dispatchEvent(new Event("click", { bubbles: true, cancelable: true }));
        await nextTick();

        expect(document.body.textContent).toContain("Your bag is empty");
    });

    it("renders line items, options and subtotal from the cart section", async () => {
        __setSection("cart", { summary_count: 2, subtotal: "$104.00", items: [ITEM] });
        const trigger = addTrigger();
        mount(MiniCart, { props: PROPS, attachTo: document.body });
        trigger.dispatchEvent(new Event("click", { bubbles: true, cancelable: true }));
        await nextTick();

        const text = document.body.textContent;
        expect(text).toContain("Chaz Hoodie");
        expect(text).toContain("Size:");
        expect(text).toContain("M");
        expect(text).toContain("$104.00");
    });

    it("increments quantity via useCart with the sidebar update URL", async () => {
        __setSection("cart", { summary_count: 2, subtotal: "$104.00", items: [ITEM] });
        const trigger = addTrigger();
        const wrapper = mount(MiniCart, { props: PROPS, attachTo: document.body });
        trigger.dispatchEvent(new Event("click", { bubbles: true, cancelable: true }));
        await nextTick();

        await wrapper.get(`[aria-label="${LABELS.increase}"]`).trigger("click");

        expect(__calls.at(-1)).toEqual({
            type: "updateItemQty",
            itemId: 15,
            qty: 3,
            action: "/checkout/sidebar/updateItemQty",
        });
    });

    it("disables the decrement control at quantity 1", async () => {
        __setSection("cart", { summary_count: 1, subtotal: "$52.00", items: [{ ...ITEM, qty: 1 }] });
        const trigger = addTrigger();
        const wrapper = mount(MiniCart, { props: PROPS, attachTo: document.body });
        trigger.dispatchEvent(new Event("click", { bubbles: true, cancelable: true }));
        await nextTick();

        expect(wrapper.get(`[aria-label="${LABELS.decrease}"]`).attributes("disabled")).toBeDefined();
    });

    it("removes a line via useCart with the sidebar remove URL", async () => {
        __setSection("cart", { summary_count: 2, subtotal: "$104.00", items: [ITEM] });
        const trigger = addTrigger();
        const wrapper = mount(MiniCart, { props: PROPS, attachTo: document.body });
        trigger.dispatchEvent(new Event("click", { bubbles: true, cancelable: true }));
        await nextTick();

        await wrapper.get(`[aria-label="${LABELS.remove} — ${ITEM.product_name}"]`).trigger("click");

        expect(__calls.at(-1)).toEqual({
            type: "removeItem",
            itemId: 15,
            action: "/checkout/sidebar/removeItem",
        });
    });

    it("points the CTAs at the cart and checkout URLs", async () => {
        __setSection("cart", { summary_count: 2, subtotal: "$104.00", items: [ITEM] });
        const trigger = addTrigger();
        const wrapper = mount(MiniCart, { props: PROPS, attachTo: document.body });
        trigger.dispatchEvent(new Event("click", { bubbles: true, cancelable: true }));
        await nextTick();

        const hrefs = wrapper.findAll("a").map((a) => a.attributes("href"));
        expect(hrefs).toContain("/checkout");
        expect(hrefs).toContain("/checkout/cart");
    });
});
