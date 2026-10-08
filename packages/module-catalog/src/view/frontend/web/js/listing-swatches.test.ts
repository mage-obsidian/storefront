// This file is part of the MageObsidian - Catalog project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect, beforeEach } from "vitest";
import { choose, selection, selectionHash } from "./listing-swatches";
import "./listing-swatches";

const URL = "https://shop.test/hoodie.html";

function build(): void {
    document.body.innerHTML = `
        <article class="product-card">
            <a class="product-card__media" href="${URL}">
                <img src="/m/default.jpg" srcset="/m/default.jpg 240w">
            </a>
            <h2><a class="name" href="${URL}">Hoodie</a></h2>
            <a class="rating" href="${URL}#reviews">(3)</a>
            <div data-listing-swatches data-product-url="${URL}">
                <div data-attribute-code="color">
                    <a class="listing-swatch" href="${URL}#color=50" data-option-id="50" data-image-src="/m/blue.jpg" data-image-srcset="/m/blue.jpg 240w"></a>
                    <a class="listing-swatch" href="${URL}#color=51" data-option-id="51" data-image-src="/m/red.jpg" data-image-srcset=""></a>
                </div>
                <div data-attribute-code="size">
                    <a class="listing-swatch" href="${URL}#size=28" data-option-id="28"></a>
                    <a class="listing-swatch" href="${URL}#size=29" data-option-id="29"></a>
                </div>
            </div>
            <div class="product-card__action"><a class="options" href="${URL}">Choose options</a></div>
        </article>
        <article class="product-card">
            <a class="product-card__media" href="https://shop.test/tee.html"><img src="/m/tee.jpg"></a>
            <div data-listing-swatches data-product-url="https://shop.test/tee.html">
                <div data-attribute-code="color">
                    <a class="listing-swatch" href="https://shop.test/tee.html#color=50" data-option-id="50" data-image-src="/m/tee-blue.jpg"></a>
                </div>
            </div>
        </article>`;
}

const swatch = (code: string, id: string, card = 0) =>
    document.querySelectorAll(".product-card")[card].querySelector(`[data-attribute-code="${code}"] [data-option-id="${id}"]`) as HTMLElement;
const image = (card = 0) => document.querySelectorAll(".product-card")[card].querySelector("img") as HTMLImageElement;
const href = (selector: string) => document.querySelector(selector)?.getAttribute("href");
const click = (element: HTMLElement, init: MouseEventInit = {}) => {
    const event = new MouseEvent("click", { bubbles: true, cancelable: true, button: 0, ...init });
    element.dispatchEvent(event);
    return event;
};

describe("listing swatches", () => {
    beforeEach(build);

    it("swaps the card image for the one of the chosen option", () => {
        click(swatch("color", "50"));

        expect(image().getAttribute("src")).toBe("/m/blue.jpg");
        expect(image().getAttribute("srcset")).toBe("/m/blue.jpg 240w");
    });

    it("drops the srcset the previous image brought when the option has none", () => {
        click(swatch("color", "51"));

        expect(image().getAttribute("src")).toBe("/m/red.jpg");
        expect(image().hasAttribute("srcset")).toBe(false);
    });

    it("restores the original image when the chosen swatch is chosen again", () => {
        click(swatch("color", "50"));
        click(swatch("color", "50"));

        expect(image().getAttribute("src")).toBe("/m/default.jpg");
        expect(image().getAttribute("srcset")).toBe("/m/default.jpg 240w");
        expect(selection(document.querySelector(".product-card")!)).toEqual([]);
    });

    it("keeps one choice per attribute", () => {
        click(swatch("color", "50"));
        click(swatch("color", "51"));

        expect(swatch("color", "50").hasAttribute("aria-current")).toBe(false);
        expect(swatch("color", "51").getAttribute("aria-current")).toBe("true");
    });

    it("carries the choice to the links that lead to the product page", () => {
        click(swatch("color", "50"));
        click(swatch("size", "29"));

        expect(href(".product-card__media")).toBe(`${URL}#color=50&size=29`);
        expect(href(".name")).toBe(`${URL}#color=50&size=29`);
        expect(href(".options")).toBe(`${URL}#color=50&size=29`);
    });

    it("leaves a link that already points inside the page alone", () => {
        click(swatch("color", "50"));

        expect(href(".rating")).toBe(`${URL}#reviews`);
    });

    it("takes the choice off the links when it is undone", () => {
        click(swatch("size", "28"));
        click(swatch("size", "28"));

        expect(href(".name")).toBe(URL);
    });

    it("does not touch another card", () => {
        click(swatch("color", "50"));

        expect(image(1).getAttribute("src")).toBe("/m/tee.jpg");
        expect(selectionHash(document.querySelectorAll(".product-card")[1])).toBe("");
    });

    it("keeps the image of the colour when only a size is chosen", () => {
        click(swatch("size", "28"));

        expect(image().getAttribute("src")).toBe("/m/default.jpg");
    });

    it("takes the click over so the card stays where it is", () => {
        expect(click(swatch("color", "50")).defaultPrevented).toBe(true);
    });

    it("lets a modified click open the product page", () => {
        expect(click(swatch("color", "50"), { ctrlKey: true }).defaultPrevented).toBe(false);
        expect(image().getAttribute("src")).toBe("/m/default.jpg");
    });

    it("exposes the choice as a hash the product page can read", () => {
        choose(swatch("color", "51"));

        expect(selectionHash(document.querySelector(".product-card")!)).toBe("#color=51");
    });
});

const PURCHASE = {
    attributes: ["size", "color"],
    variants: {
        "28,50": { price: "$49.00", id: 101 },
        "29,50": { price: "$59.00", id: 102 },
    },
};

function buildPurchasable(): void {
    document.body.innerHTML = `
        <article class="product-card">
            <div class="product-card__price"><span class="price">From $49.00</span></div>
            <div data-listing-swatches data-product-url="${URL}" data-purchase='${JSON.stringify(PURCHASE)}'>
                <div data-attribute-code="size">
                    <a class="listing-swatch" href="${URL}#size=28" data-option-id="28"></a>
                    <a class="listing-swatch" href="${URL}#size=29" data-option-id="29"></a>
                    <a class="listing-swatch" href="${URL}#size=30" data-option-id="30"></a>
                </div>
                <div data-attribute-code="color">
                    <a class="listing-swatch" href="${URL}#color=50" data-option-id="50"></a>
                    <a class="listing-swatch" href="${URL}#color=51" data-option-id="51"></a>
                </div>
                <form data-add-to-cart data-listing-purchase action="/cart/add" method="post" hidden>
                    <input type="hidden" name="product" value="7">
                    <input type="hidden" name="super_attribute[141]" data-attribute-code="size" disabled>
                    <input type="hidden" name="super_attribute[93]" data-attribute-code="color" disabled>
                    <button type="submit">Add to cart</button>
                </form>
                <span data-listing-unavailable hidden>Not available</span>
            </div>
            <div class="product-card__action"><a class="options" href="${URL}">Choose options</a></div>
        </article>`;
}

const form = () => document.querySelector<HTMLFormElement>("[data-listing-purchase]")!;
const unavailable = () => document.querySelector<HTMLElement>("[data-listing-unavailable]")!;
const price = () => document.querySelector(".product-card__price")!.textContent?.trim();
const action = () => document.querySelector(".product-card__action")!;
const input = (code: string) => document.querySelector<HTMLInputElement>(`[data-listing-purchase] input[data-attribute-code="${code}"]`)!;

describe("buying a full variant from the card", () => {
    beforeEach(buildPurchasable);

    it("keeps the link to the product page while an option is missing", () => {
        click(swatch("size", "29"));

        expect(form().hidden).toBe(true);
        expect(unavailable().hidden).toBe(true);
        expect(action().querySelector(".options")).not.toBeNull();
        expect(price()).toBe("From $49.00");
    });

    it("shows the price of the variant once every option is chosen", () => {
        click(swatch("size", "29"));
        click(swatch("color", "50"));

        expect(price()).toBe("$59.00");
    });

    it("offers to add that exact variant", () => {
        click(swatch("size", "29"));
        click(swatch("color", "50"));

        expect(form().hidden).toBe(false);
        expect(action().contains(form())).toBe(true);
        expect(form().dataset.variantId).toBe("102");
        expect(input("size").value).toBe("29");
        expect(input("color").value).toBe("50");
        expect(input("size").disabled).toBe(false);
        expect(input("color").disabled).toBe(false);
        expect((action().querySelector(".options") as HTMLElement | null)?.hidden ?? true).toBe(true);
    });

    it("says the combination is not available and offers nothing to add", () => {
        click(swatch("size", "30"));
        click(swatch("color", "51"));

        expect(unavailable().hidden).toBe(false);
        expect(action().contains(unavailable())).toBe(true);
        expect(form().hidden).toBe(true);
        expect(input("size").disabled).toBe(true);
        expect(price()).toBe("From $49.00");
    });

    it("goes back to the link and the original price when an option is undone", () => {
        click(swatch("size", "29"));
        click(swatch("color", "50"));
        click(swatch("color", "50"));

        expect(form().hidden).toBe(true);
        expect(unavailable().hidden).toBe(true);
        expect((action().querySelector(".options") as HTMLElement).hidden).toBe(false);
        expect(price()).toBe("From $49.00");
        expect(input("size").disabled).toBe(true);
    });

    it("follows a change of option between two available variants", () => {
        click(swatch("size", "29"));
        click(swatch("color", "50"));
        click(swatch("size", "28"));

        expect(price()).toBe("$49.00");
        expect(form().dataset.variantId).toBe("101");
        expect(input("size").value).toBe("28");
    });

    it("does nothing for a card without a purchase map", () => {
        build();
        click(swatch("color", "50"));
        click(swatch("size", "29"));

        expect(document.querySelector("[data-listing-purchase]")).toBeNull();
        expect(href(".options")).toBe(`${URL}#color=50&size=29`);
    });
});
