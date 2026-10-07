// This file is part of the MageObsidian - Checkout project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import PurchaseOrder from "./PurchaseOrder.vue";

const CONFIG = { labels: { poNumber: "Purchase Order Number", required: "Enter the purchase order number." } };

function render(props: Record<string, unknown> = {}) {
    return mount(PurchaseOrder, {
        props: { code: "purchaseorder", title: "Purchase Order", selected: true, config: CONFIG, customerData: {}, ...props },
    });
}

const last = (wrapper: ReturnType<typeof render>, event: string) => {
    const calls = wrapper.emitted(event) ?? [];
    return calls[calls.length - 1];
};

describe("PurchaseOrder payment renderer", () => {
    it("draws the method as a radio card with its title", () => {
        const wrapper = render({ selected: false });

        expect(wrapper.find(".field-radio-card").text()).toContain("Purchase Order");
    });

    it("asks the checkout to select it when the shopper picks it", async () => {
        const wrapper = render({ selected: false });

        await wrapper.find("input[type=radio]").trigger("change");

        expect(wrapper.emitted("select")).toHaveLength(1);
    });

    it("asks for the number only while selected", () => {
        expect(render({ selected: false }).find("[data-purchase-order] input").exists()).toBe(false);
        expect(render().find("[data-purchase-order] input").exists()).toBe(true);
    });

    it("labels the field with the store's own wording", () => {
        expect(render().find("[data-purchase-order] label").text()).toContain("Purchase Order Number");
    });

    it("is not ready until a number is typed, and says why", () => {
        const wrapper = render();

        expect(last(wrapper, "ready")).toEqual([false, "Enter the purchase order number."]);
    });

    it("publishes the number and becomes ready once it is typed", async () => {
        const wrapper = render();

        await wrapper.find("[data-purchase-order] input").setValue("PO-1234");

        expect(last(wrapper, "data")).toEqual([{ po_number: "PO-1234" }]);
        expect(last(wrapper, "ready")).toEqual([true, ""]);
    });

    it("trims the number and treats blanks as missing", async () => {
        const wrapper = render();

        await wrapper.find("[data-purchase-order] input").setValue("   ");

        expect(last(wrapper, "ready")).toEqual([false, "Enter the purchase order number."]);
        expect(last(wrapper, "data")).toEqual([{}]);

        await wrapper.find("[data-purchase-order] input").setValue("  PO-9  ");
        expect(last(wrapper, "data")).toEqual([{ po_number: "PO-9" }]);
    });
});
