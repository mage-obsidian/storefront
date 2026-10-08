// This file is part of the MageObsidian - Catalog project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect, beforeEach, vi } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import ProductForm from "./ProductForm.vue";
import { __rawCalls, __reset, __setResult } from "MageObsidian_Storefront::js/useCart";
import events from "MageObsidian_ModernFrontend::js/events";
import { CatalogEvent } from "MageObsidian_Catalog::js/catalog-events";
import { NOTIFICATION_EVENT } from "MageObsidian_Storefront::js/notifications";

// Configurable buy-box island. We assert the selection contract: a radiogroup per
// attribute, impossible combinations greyed out, full selection drives price +
// gallery image, and add-to-cart delegates super_attribute to the cart and
// announces a toast. The cart POST itself (form key, body shape, section reload)
// is owned by module-storefront's useCart, mocked here at the module boundary.

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
    __reset();
    events.reset();
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

    it("picks up the options a listing card carried in the address", async () => {
        window.location.hash = "#color=5&size=7";
        const wrapper = build();
        await flushPromises();
        window.location.hash = "";

        expect(wrapper.find('[data-option-id="5"]').attributes("aria-checked")).toBe("true");
        expect(wrapper.find('[data-option-id="7"]').attributes("aria-checked")).toBe("true");
        expect(wrapper.find("button[type=submit]").text()).toBe("Add to cart");
    });

    it("ignores an address option the product does not offer", async () => {
        window.location.hash = "#color=99&size=7&weight=1";
        const wrapper = build();
        await flushPromises();
        window.location.hash = "";

        expect(wrapper.find('[data-option-id="7"]').attributes("aria-checked")).toBe("true");
        expect(wrapper.findAll('[aria-checked="true"]')).toHaveLength(1);
    });

    it("leaves the choice empty when the address names no option", async () => {
        window.location.hash = "#reviews";
        const wrapper = build();
        await flushPromises();
        window.location.hash = "";

        expect(wrapper.findAll('[aria-checked="true"]')).toHaveLength(0);
    });

    it("greys impossible combinations once one attribute is chosen", async () => {
        const wrapper = build();
        await wrapper.find('[data-option-id="5"]').trigger("click"); // Red

        // Red only pairs with S (7); M (8) becomes impossible.
        expect(wrapper.find('[data-option-id="7"]').attributes("disabled")).toBeUndefined();
        expect(wrapper.find('[data-option-id="8"]').attributes("disabled")).toBeDefined();
    });

    it("updates price, swaps the gallery image and delegates super_attribute on full selection", async () => {
        const variantImage = vi.fn();
        events.observe(CatalogEvent.ProductGalleryChange, variantImage);
        const toast = vi.fn();
        events.observe(NOTIFICATION_EVENT, toast);

        const wrapper = build();
        await wrapper.find('[data-option-id="5"]').trigger("click"); // Red
        await wrapper.find('[data-option-id="7"]').trigger("click"); // S → variant 11

        expect(wrapper.text()).toContain("$25.00"); // old price struck through
        expect(wrapper.find("button[type=submit]").text()).toBe("Add to cart");
        expect(variantImage).toHaveBeenCalledTimes(1);
        expect(variantImage.mock.calls[0][0].large).toBe("/red.jpg");

        await wrapper.find("form").trigger("submit");
        await flushPromises();

        expect(__rawCalls).toHaveLength(1);
        expect(__rawCalls[0].action).toBe("/checkout/cart/add");
        const body = __rawCalls[0].body;
        expect(body.get("product")).toBe("7");
        expect(body.get("uenc")).toBe("ENC");
        expect(body.get("super_attribute[93]")).toBe("5");
        expect(body.get("super_attribute[144]")).toBe("7");
        expect(toast).toHaveBeenCalledTimes(1);
        expect(toast.mock.calls[0][0].message).toBe("Added");
    });

    it("announces the failure label when the cart rejects the add", async () => {
        __setResult(false);
        const toast = vi.fn();
        events.observe(NOTIFICATION_EVENT, toast);

        const wrapper = build();
        await wrapper.find('[data-option-id="6"]').trigger("click"); // Blue
        await wrapper.find('[data-option-id="8"]').trigger("click"); // M → variant 12
        await wrapper.find("form").trigger("submit");
        await flushPromises();

        expect(__rawCalls).toHaveLength(1);
        expect(toast.mock.calls.at(-1)[0].message).toBe("Failed");
    });

    it("stays quiet when the bag already announced the add", async () => {
        __setResult(true, undefined, true);
        const toast = vi.fn();
        events.observe(NOTIFICATION_EVENT, toast);

        const wrapper = build();
        await wrapper.find('[data-option-id="6"]').trigger("click");
        await wrapper.find('[data-option-id="8"]').trigger("click");
        await wrapper.find("form").trigger("submit");
        await flushPromises();

        expect(__rawCalls).toHaveLength(1);
        expect(toast).not.toHaveBeenCalled();
    });
});

describe("variant announcements", () => {
    it("announces the resolved child once the selection is complete", async () => {
        const wrapper = build();

        await wrapper.find('[data-option-id="5"]').trigger("click");
        await wrapper.find('[data-option-id="7"]').trigger("click");
        await flushPromises();

        expect(events.recorded("product_variant_change")).toEqual([{ productId: 11 }]);
        wrapper.unmount();
    });
});

describe("ProductForm — prices with and without tax", () => {
    function taxedConfig() {
        const parsed = JSON.parse(config());
        parsed.attributes[93].options[0].products = ["11", "13"];
        parsed.attributes[144].options[1].products = ["12", "13"];
        parsed.index[13] = { 93: "5", 144: "8" };
        parsed.optionPrices = {
            11: { finalPrice: { amount: 21.6 }, basePrice: { amount: 20 }, oldPrice: { amount: 21.6 } },
            12: { finalPrice: { amount: 32.4 }, basePrice: { amount: 30 }, oldPrice: { amount: 32.4 } },
            13: { finalPrice: { amount: 43.2 }, basePrice: { amount: 40 }, oldPrice: { amount: 43.2 } },
        };
        return JSON.stringify(parsed);
    }

    function taxed(priceDisplay) {
        return mount(ProductForm, {
            props: {
                config: taxedConfig(),
                swatches: swatches(),
                productId: 7,
                action: "/checkout/cart/add",
                uenc: "ENC",
                initialPrice: "$21.60",
                initialPriceExcl: "$20.00",
                priceDisplay,
                labels: { ...LABELS, inclTax: "Incl. Tax", exclTax: "Excl. Tax" },
            },
        });
    }

    const basis = (wrapper) =>
        wrapper.findAll("[data-price-basis]").map((node) => [node.attributes("data-price-basis"), node.find(".price").text()]);

    it("states both amounts before any variant is chosen", () => {
        expect(basis(taxed("both"))).toEqual([
            ["incl", "$21.60"],
            ["excl", "$20.00"],
        ]);
    });

    it("keeps stating both amounts of the variant the buyer chooses", async () => {
        const wrapper = taxed("both");
        await wrapper.find('[data-option-id="5"]').trigger("click");
        await wrapper.find('[data-option-id="7"]').trigger("click");

        expect(basis(wrapper)).toEqual([
            ["incl", "$21.60"],
            ["excl", "$20.00"],
        ]);

        await wrapper.find('[data-option-id="8"]').trigger("click");

        expect(basis(wrapper)).toEqual([
            ["incl", "$43.20"],
            ["excl", "$40.00"],
        ]);
    });

    it("labels each amount", () => {
        const text = taxed("both").text();

        expect(text).toContain("Incl. Tax");
        expect(text).toContain("Excl. Tax");
    });

    it("states a single amount when the store shows the price with tax only", async () => {
        const wrapper = taxed("incl");
        await wrapper.find('[data-option-id="5"]').trigger("click");
        await wrapper.find('[data-option-id="7"]').trigger("click");

        expect(wrapper.findAll("[data-price-basis]")).toHaveLength(0);
        expect(wrapper.find(".pdp__price-final").text()).toBe("$21.60");
    });

    it("states a single amount when the store shows the price without tax", async () => {
        const wrapper = taxed("excl");

        expect(wrapper.findAll("[data-price-basis]")).toHaveLength(0);
        expect(wrapper.find(".pdp__price-final").text()).toBe("$21.60");
    });
});


describe("ProductForm — quantity discounts", () => {
    const TIER_LABEL = "Buy %1 for %2 each and save %3%";

    function tiered() {
        const parsed = JSON.parse(config());
        parsed.optionPrices[11].tierPrices = [
            { qty: 5, price: 16, percentage: 20 },
            { qty: 10, price: 14, percentage: 30 },
        ];
        return mount(ProductForm, {
            props: {
                config: JSON.stringify(parsed),
                swatches: swatches(),
                productId: 7,
                action: "/checkout/cart/add",
                uenc: "ENC",
                initialPrice: "$20.00",
                labels: { ...LABELS, tierPrice: TIER_LABEL },
            },
        });
    }

    const lines = (wrapper) => wrapper.findAll(".prices-tier li").map((node) => node.text());

    it("announces nothing before a variant is chosen", () => {
        expect(lines(tiered())).toEqual([]);
    });

    it("announces each tier of the chosen variant with its unit price and saving", async () => {
        const wrapper = tiered();
        await wrapper.find('[data-option-id="5"]').trigger("click");
        await wrapper.find('[data-option-id="7"]').trigger("click");

        expect(lines(wrapper)).toEqual([
            "Buy 5 for $16.00 each and save 20%",
            "Buy 10 for $14.00 each and save 30%",
        ]);
    });

    it("drops the announcement when the chosen variant has no tiers", async () => {
        const wrapper = tiered();
        await wrapper.find('[data-option-id="6"]').trigger("click");
        await wrapper.find('[data-option-id="8"]').trigger("click");

        expect(lines(wrapper)).toEqual([]);
    });
});

describe("ProductForm — quantity increments", () => {
    function sold(qtyMin, qtyStep, initialQty) {
        return mount(ProductForm, {
            props: {
                config: config(),
                swatches: swatches(),
                productId: 7,
                action: "/checkout/cart/add",
                uenc: "ENC",
                initialPrice: "$20.00",
                qtyMin,
                qtyStep,
                initialQty,
                labels: LABELS,
            },
        });
    }

    it("lets the quantity field advance in the product's increments from its minimum", () => {
        const field = sold(6, 6, 6).find("#pdp-cfg-qty");

        expect(field.attributes("min")).toBe("6");
        expect(field.attributes("step")).toBe("6");
        expect(field.element.value).toBe("6");
    });

    it("keeps whole units when the product has no increments", () => {
        const field = build().find("#pdp-cfg-qty");

        expect(field.attributes("min")).toBe("1");
        expect(field.attributes("step")).toBe("1");
    });
});
