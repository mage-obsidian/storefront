import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { nextTick } from "vue";
import MiniCart from "./MiniCart.vue";
import { __setSection, __reset } from "MageObsidian_ModernFrontend::js/customer-data";
import { __calls, __reset as __resetCart, __setResult } from "MageObsidian_Storefront::js/useCart";
import events, { __reset as __resetEvents } from "MageObsidian_ModernFrontend::js/events";
import { NOTIFICATION_EVENT, NotificationTone } from "MageObsidian_Storefront::js/notifications";

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

    it("flattens array option values and strips price markup (bundle/downloadable)", async () => {
        const bundle = {
            ...ITEM,
            item_id: 16,
            product_name: "Sprite Yoga Kit",
            options: [
                { label: "Sprite Stasis Ball", value: ['1 x Sprite Stasis Ball <span class="price">$27.00</span>'] },
                { label: "Downloads", value: ["Episode 1", "Episode 2"] },
            ],
        };
        __setSection("cart", { summary_count: 1, subtotal: "$65.00", items: [bundle] });
        const trigger = addTrigger();
        mount(MiniCart, { props: PROPS, attachTo: document.body });
        trigger.dispatchEvent(new Event("click", { bubbles: true, cancelable: true }));
        await nextTick();

        const text = document.body.textContent;
        expect(text).toContain("1 x Sprite Stasis Ball $27.00");
        expect(text).not.toContain("<span");
        expect(text).not.toContain('["');
        expect(text).toContain("Episode 1, Episode 2");
    });

    it("increments quantity via useCart with the sidebar update URL", async () => {
        __setSection("cart", { summary_count: 2, subtotal: "$104.00", items: [ITEM] });
        const trigger = addTrigger();
        const wrapper = mount(MiniCart, { props: PROPS, attachTo: document.body });
        trigger.dispatchEvent(new Event("click", { bubbles: true, cancelable: true }));
        await nextTick();

        await wrapper.get(`[aria-label="${LABELS.increase}"]`).trigger("click");

        // Coalesced: the request trails the click by the stepper's debounce.
        await vi.waitFor(() =>
            expect(__calls.at(-1)).toEqual({
                type: "updateItemQty",
                itemId: 15,
                qty: 3,
                action: "/checkout/sidebar/updateItemQty",
            }),
        );
    });

    it("sends one request carrying the last value when the stepper is hammered", async () => {
        __setSection("cart", { summary_count: 2, subtotal: "$104.00", items: [ITEM] });
        const trigger = addTrigger();
        const wrapper = mount(MiniCart, { props: PROPS, attachTo: document.body });
        trigger.dispatchEvent(new Event("click", { bubbles: true, cancelable: true }));
        await nextTick();

        const increase = wrapper.get(`[aria-label="${LABELS.increase}"]`);
        for (let click = 0; click < 5; click += 1) {
            await increase.trigger("click");
        }

        await vi.waitFor(() => expect(__calls).toHaveLength(1));
        expect(__calls[0]).toMatchObject({ type: "updateItemQty", itemId: 15, qty: 7 });
        expect(wrapper.findAll("input")[0].element.value).toBe("7");
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

describe("MiniCart optimistic mutations", () => {
    const OTHER = { ...ITEM, item_id: 16, product_name: "Aero Pant", qty: 1 };

    beforeEach(() => {
        __reset();
        __resetCart();
        __resetEvents();
        delete window.__MAGE_OBSIDIAN_UX__;
    });

    async function openBag(items = [ITEM, OTHER], summary = 3) {
        __setSection("cart", { items, summary_count: summary, subtotal: "$104.00" });
        const trigger = addTrigger();
        const wrapper = mount(MiniCart, { props: PROPS, attachTo: document.body });
        await nextTick();
        trigger.click();
        await nextTick();
        return wrapper;
    }

    it("drops the row and the count before the server answers", async () => {
        const wrapper = await openBag();

        await wrapper.findAll(".minicart-remove")[0].trigger("click");
        await nextTick();

        expect(wrapper.findAll("li.minicart-item")).toHaveLength(1);
        expect(wrapper.text()).not.toContain("Chaz Hoodie");
        expect(wrapper.get("h2").text()).toContain("(1)");
        expect(__calls).toEqual([
            { type: "removeItem", itemId: 15, action: PROPS.removeUrl },
        ]);
    });

    it("brings the row back with a warning when the server refuses", async () => {
        const toasts = [];
        events.observe(NOTIFICATION_EVENT, (data) => toasts.push(data));
        __setResult(false, "Item could not be removed");
        const wrapper = await openBag();

        await wrapper.findAll(".minicart-remove")[0].trigger("click");
        await flushPromises();

        expect(wrapper.findAll("li.minicart-item")).toHaveLength(2);
        expect(wrapper.text()).toContain("Chaz Hoodie");
        expect(toasts).toEqual([
            { message: "Item could not be removed", tone: NotificationTone.Warning },
        ]);
    });

    it("waits for the server when the merchant turned optimistic UI off", async () => {
        window.__MAGE_OBSIDIAN_UX__ = { optimistic: false, summaryCountsQty: true };
        const wrapper = await openBag();

        await wrapper.findAll(".minicart-remove")[0].trigger("click");
        await nextTick();

        expect(wrapper.findAll("li.minicart-item")).toHaveLength(2);
        expect(__calls).toHaveLength(1);
    });

    it("moves the quantity and the count by the delta right away", async () => {
        const wrapper = await openBag();

        await wrapper.findAll('[aria-label^="Increase"]')[0].trigger("click");
        await nextTick();

        expect(wrapper.findAll("input")[0].element.value).toBe("3");
        expect(wrapper.get("h2").text()).toContain("(4)");
        await vi.waitFor(() =>
            expect(__calls).toEqual([
                { type: "updateItemQty", itemId: 15, qty: 3, action: PROPS.updateUrl },
            ]),
        );
    });

    it("counts a removed line as one when the badge counts lines", async () => {
        window.__MAGE_OBSIDIAN_UX__ = { optimistic: true, summaryCountsQty: false };
        const wrapper = await openBag();

        await wrapper.findAll(".minicart-remove")[0].trigger("click");
        await nextTick();

        expect(wrapper.get("h2").text()).toContain("(2)");
    });

    it("marks the subtotal as syncing while a mutation is in flight", async () => {
        const wrapper = await openBag();

        wrapper.findAll(".minicart-remove")[0].trigger("click");
        await nextTick();

        expect(wrapper.get("footer .minicart-value").classes()).toContain("is-syncing");

        await flushPromises();
        await nextTick();
        expect(wrapper.get("footer .minicart-value").classes()).not.toContain("is-syncing");
    });

    it("removes with a trash icon rather than an inline svg path", async () => {
        const wrapper = await openBag();

        expect(wrapper.get(".minicart-remove svg").attributes("data-icon")).toBe("trash");
    });
});
