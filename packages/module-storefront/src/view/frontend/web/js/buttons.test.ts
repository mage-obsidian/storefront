import { beforeEach, describe, expect, it, vi } from "vitest";
import { buttonsIn, enhanceButtons, equaliseButtons, widestOf } from "./buttons.ts";

const build = (sameWidth: boolean, widths: number[]): HTMLElement => {
    document.body.innerHTML = `
        <div data-content-type="buttons" data-same-width="${sameWidth}">
            ${widths.map(() => '<div data-content-type="button-item"><a data-element="link">Go</a></div>').join("")}
        </div>`;
    const group = document.querySelector('[data-content-type="buttons"]') as HTMLElement;
    buttonsIn(group).forEach((button, index) => {
        button.getBoundingClientRect = vi.fn(() => ({ width: widths[index] }) as DOMRect);
    });

    return group;
};

const widthsIn = (group: Element): string[] => buttonsIn(group).map((button) => button.style.width);

beforeEach(() => {
    document.body.innerHTML = "";
});

describe("buttonsIn", () => {
    it("reaches the button the visitor sees, not the box around it", () => {
        const group = build(true, [80, 140]);

        expect(buttonsIn(group).map((button) => button.tagName)).toEqual(["A", "A"]);
    });
});

describe("widestOf", () => {
    it("is the widest of what it was given", () => {
        expect(widestOf([80, 140, 100])).toBe(140);
    });

    it("is zero when it was given nothing", () => {
        expect(widestOf([])).toBe(0);
    });
});

describe("equaliseButtons", () => {
    it("gives every button the width of the widest", () => {
        const group = build(true, [80, 140, 100]);

        equaliseButtons(group);

        expect(widthsIn(group)).toEqual(["140px", "140px", "140px"]);
    });

    it("leaves a lone button at its own width", () => {
        const group = build(true, [80]);

        equaliseButtons(group);

        expect(widthsIn(group)).toEqual([""]);
    });

    it("leaves buttons alone when nothing can be measured", () => {
        const group = build(true, [0, 0]);

        equaliseButtons(group);

        expect(widthsIn(group)).toEqual(["", ""]);
    });
});

describe("enhanceButtons", () => {
    it("equalises a group the author asked to be uniform", () => {
        const group = build(true, [80, 140]);

        enhanceButtons(document);

        expect(widthsIn(group)).toEqual(["140px", "140px"]);
    });

    it("leaves each button at its own width when the author did not ask", () => {
        const group = build(false, [80, 140]);

        enhanceButtons(document);

        expect(widthsIn(group)).toEqual(["", ""]);
    });
});
