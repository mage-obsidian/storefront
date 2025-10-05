import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import ProductForm from "./ProductForm.vue";

// Configurable buy-box island. We assert the selection contract: a radiogroup per
// attribute, impossible combinations greyed out, full selection drives price +
// gallery image, and add-to-cart posts super_attribute and announces a toast.

const LABELS = {
    chooseFor: "Choose %1",
    addToCart: "Add to cart",
    selectOptions: "Select options",
    qty: "Qty",
    added: "Added",
    failed: "Failed",
};

// One color attribute (93) × one size attribute (144), two real variants:
//   variant 11 = Red / S, variant 12 = Blue / M.
// So Red only pairs with S and Blue only with M (the cross pairs are impossible).
function config() {
    return JSON.stringify({
        attributes: {
            93: {
                id: "93", code: "color", label: "Color", position: 0,
                options: [
                    { id: "5", label: "Red", products: ["11"] },
                    { id: "6", label: "Blue", products: ["12"] },
                ],
            },
            144: {
                id: "144", code: "size", label: "Size", position: 1,
                options: [
                    { id: "7", label: "S", products: ["11"] },
                    { id: "8", label: "M", products: ["12"] },
                ],
            },
        },
        index: {
            11: { 93: "5", 144: "7" },
            12: { 93: "6", 144: "8" },
        },
        optionPrices: {
            11: { finalPrice: { amount: 20 }, oldPrice: { amount: 25 } },
            12: { finalPrice: { amount: 30 }, oldPrice: { amount: 30 } },
        },
        currencyFormat: "$%s",
        images: { 11: [{ full: "/red.jpg", img: "/red.jpg", isMain: true, caption: "Red tee" }] },
        productId: 7,
    });
}

function swatches() {
    return JSON.stringify({
        93: {
            5: { type: "1", value: "#ff0000", label: "Red" },
            6: { type: "1", value: "#0000ff", label: "Blue" },
        },
    });
}

function build() {
    return mount(ProductForm, {
        props: {
            config: config(),
            swatches: swatches(),
            productId: 7,
            action: "/checkout/cart/add",
            uenc: "ENC",
            initialPrice: "$20.00",
            labels: LABELS,
        },
    });
}

beforeEach(() => {
    document.cookie = "form_key=ck";
});
afterEach(() => {
    vi.unstubAllGlobals();
});

describe("ProductForm", () => {
    it("renders a radiogroup per attribute", () => {
        const wrapper = build();
        const groups = wrapper.findAll("[role=radiogroup]");
        expect(groups).toHaveLength(2);
        expect(wrapper.findAll('[role=radio]')).toHaveLength(4);
    });

    it("shows the initial price and a select-options button before any choice", () => {
        const wrapper = build();
        expect(wrapper.text()).toContain("$20.00");
        expect(wrapper.find("button[type=submit]").text()).toBe("Select options");
    });

    it("greys impossible combinations once one attribute is chosen", async () => {
        const wrapper = build();
        await wrapper.find('[data-option-id="5"]').trigger("click"); // Red

        // Red only pairs with S (7); M (8) becomes impossible.
        expect(wrapper.find('[data-option-id="7"]').attributes("disabled")).toBeUndefined();
        expect(wrapper.find('[data-option-id="8"]').attributes("disabled")).toBeDefined();
    });

    it("updates price, swaps the gallery image and posts super_attribute on full selection", async () => {
        const variantImage = vi.fn();
        window.addEventListener("obsidian:variant-image", variantImage);
        const toast = vi.fn();
        window.addEventListener("obsidian:toast", toast);
        const fetchMock = vi.fn().mockResolvedValue({ ok: true });
        vi.stubGlobal("fetch", fetchMock);

        const wrapper = build();
        await wrapper.find('[data-option-id="5"]').trigger("click"); // Red
        await wrapper.find('[data-option-id="7"]').trigger("click"); // S → variant 11

        expect(wrapper.text()).toContain("$25.00"); // old price struck through
        expect(wrapper.find("button[type=submit]").text()).toBe("Add to cart");
        expect(variantImage).toHaveBeenCalledTimes(1);
        expect(variantImage.mock.calls[0][0].detail.large).toBe("/red.jpg");

        await wrapper.find("form").trigger("submit");
        await flushPromises();

        const body = fetchMock.mock.calls.at(-1)[1].body;
        expect(body.get("product")).toBe("7");
        expect(body.get("form_key")).toBe("ck");
        expect(body.get("super_attribute[93]")).toBe("5");
        expect(body.get("super_attribute[144]")).toBe("7");
        expect(toast).toHaveBeenCalledTimes(1);
        expect(toast.mock.calls[0][0].detail.message).toBe("Added");

        window.removeEventListener("obsidian:variant-image", variantImage);
        window.removeEventListener("obsidian:toast", toast);
    });
});
