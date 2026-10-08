// This file is part of the MageObsidian - Catalog project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect, beforeEach } from "vitest";
import { setup, total } from "./downloadable-links";

function build(): HTMLFormElement {
    document.body.innerHTML = `
        <article data-pdp>
            <div class="pdp__price">
                <div class="price-box">
                    <span data-price-amount="20" class="price-wrapper"><span class="price">$20.00</span></span>
                </div>
            </div>
            <form data-downloadable-links data-base-price="20" data-currency-format="$%s">
                <label class="pdp__links-all"><input type="checkbox" data-links-select-all></label>
                <input type="checkbox" name="links[]" value="1" data-link-price="10">
                <input type="checkbox" name="links[]" value="2" data-link-price="5.5">
                <input type="checkbox" name="links[]" value="3" data-link-price="2">
            </form>
        </article>`;
    return document.querySelector("form") as HTMLFormElement;
}

const boxes = (form: HTMLFormElement) => Array.from(form.querySelectorAll<HTMLInputElement>("input[data-link-price]"));
const all = (form: HTMLFormElement) => form.querySelector<HTMLInputElement>("[data-links-select-all]") as HTMLInputElement;
const shown = () => document.querySelector(".price")?.textContent;
const toggle = (box: HTMLInputElement, checked: boolean) => {
    box.checked = checked;
    box.dispatchEvent(new Event("change", { bubbles: true }));
};

describe("downloadable link pricing", () => {
    let form: HTMLFormElement;

    beforeEach(() => {
        form = build();
        setup(form);
    });

    it("adds the price of each chosen link to the base price", () => {
        toggle(boxes(form)[0], true);
        expect(shown()).toBe("$30.00");

        toggle(boxes(form)[1], true);
        expect(shown()).toBe("$35.50");
    });

    it("takes a link off the price when it is unchosen", () => {
        toggle(boxes(form)[0], true);
        toggle(boxes(form)[1], true);
        toggle(boxes(form)[0], false);

        expect(shown()).toBe("$25.50");
    });

    it("keeps the price attribute in step with the text", () => {
        toggle(boxes(form)[2], true);

        expect(document.querySelector("[data-price-amount]")?.getAttribute("data-price-amount")).toBe("22");
    });

    it("chooses every link at once and sums them", () => {
        toggle(all(form), true);

        expect(boxes(form).every((box) => box.checked)).toBe(true);
        expect(shown()).toBe("$37.50");
    });

    it("clears every link when select all is cleared", () => {
        toggle(all(form), true);
        toggle(all(form), false);

        expect(boxes(form).some((box) => box.checked)).toBe(false);
        expect(shown()).toBe("$20.00");
    });

    it("follows the links chosen one by one", () => {
        boxes(form).forEach((box) => toggle(box, true));
        expect(all(form).checked).toBe(true);

        toggle(boxes(form)[1], false);
        expect(all(form).checked).toBe(false);
        expect(all(form).indeterminate).toBe(true);

        toggle(boxes(form)[0], false);
        toggle(boxes(form)[2], false);
        expect(all(form).indeterminate).toBe(false);
    });

    it("rounds away float noise when summing", () => {
        expect(total(0.1, [0.2])).toBe(0.3);
    });

    it("leaves a page without a price box alone", () => {
        document.body.innerHTML = `<form data-downloadable-links data-base-price="1"><input type="checkbox" data-link-price="2"></form>`;
        const bare = document.querySelector("form") as HTMLFormElement;

        expect(() => {
            setup(bare);
            toggle(bare.querySelector("input") as HTMLInputElement, true);
        }).not.toThrow();
    });
});
