// This file is part of the MageObsidian - Checkout project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import Instructions from "./Instructions.vue";

const CHEQUE_CONFIG = {
    payableTo: "Obsidian Fixtures Ltd",
    mailingAddress: "1 Cheque Lane\nDallas, TX 75201",
    labels: { payableTo: "Make check payable to", mailingAddress: "Send check to" },
};

function render(props: Record<string, unknown> = {}) {
    return mount(Instructions, {
        props: { code: "checkmo", title: "Check / Money order", selected: true, config: CHEQUE_CONFIG, customerData: {}, ...props },
    });
}

describe("Instructions payment renderer", () => {
    it("draws the method as a radio card with its title", () => {
        const wrapper = render({ selected: false });

        expect(wrapper.find(".field-radio-card").text()).toContain("Check / Money order");
        expect(wrapper.find("input[type=radio]").exists()).toBe(true);
    });

    it("asks the checkout to select it when the shopper picks it", async () => {
        const wrapper = render({ selected: false });

        await wrapper.find("input[type=radio]").trigger("change");

        expect(wrapper.emitted("select")).toHaveLength(1);
    });

    it("shows who the cheque is payable to and where to send it once selected", () => {
        const details = render().find('[data-payment-instructions="checkmo"]');

        expect(details.text()).toContain("Make check payable to");
        expect(details.text()).toContain("Obsidian Fixtures Ltd");
        expect(details.text()).toContain("Send check to");
        expect(details.text()).toContain("1 Cheque Lane");
        expect(details.text()).toContain("Dallas, TX 75201");
    });

    it("keeps the line breaks of the address, one line each", () => {
        const lines = render().findAll('[data-payment-instructions="checkmo"] [data-line]');

        expect(lines.map((line) => line.text())).toEqual(["Obsidian Fixtures Ltd", "1 Cheque Lane", "Dallas, TX 75201"]);
    });

    it("shows the instructions of a transfer, line by line", () => {
        const wrapper = render({
            code: "banktransfer",
            title: "Bank Transfer",
            config: { instructions: "Wire to IBAN FX00.\nQuote your order." },
        });

        const lines = wrapper.findAll('[data-payment-instructions="banktransfer"] [data-line]');
        expect(lines.map((line) => line.text())).toEqual(["Wire to IBAN FX00.", "Quote your order."]);
    });

    it("never interprets the text as markup", () => {
        const wrapper = render({ code: "banktransfer", config: { instructions: "<img src=x onerror=alert(1)>" } });

        expect(wrapper.find("img").exists()).toBe(false);
        expect(wrapper.text()).toContain("<img src=x onerror=alert(1)>");
    });

    it("shows nothing while the method is not selected", () => {
        expect(render({ selected: false }).find("[data-payment-instructions]").exists()).toBe(false);
    });

    it("shows nothing for a method that has nothing to say", () => {
        const wrapper = render({ code: "cashondelivery", config: {} });

        expect(wrapper.find("[data-payment-instructions]").exists()).toBe(false);
    });

    it("reports itself ready as soon as it is selected", () => {
        const wrapper = render();

        expect(wrapper.emitted("ready")?.[0]).toEqual([true, ""]);
    });
});
