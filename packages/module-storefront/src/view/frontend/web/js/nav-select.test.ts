import { describe, it, expect, beforeEach, vi } from "vitest";
import { bindNavSelect, bindNavSelects } from "./nav-select.ts";

function select(options: string[], selected = 0): HTMLSelectElement {
    document.body.innerHTML = `<select data-nav-select>${options
        .map(
            (value, index) =>
                `<option value="${value}"${index === selected ? " selected" : ""}>x</option>`,
        )
        .join("")}</select>`;
    return document.querySelector("select") as HTMLSelectElement;
}

beforeEach(() => {
    document.body.innerHTML = "";
});

describe("bindNavSelect", () => {
    it("navigates to the option's URL on change", () => {
        const element = select(["/a", "/b"]);
        const navigate = vi.fn();

        bindNavSelect(element, navigate);
        element.value = "/b";
        element.dispatchEvent(new Event("change"));

        expect(navigate).toHaveBeenCalledWith("/b");
    });

    it("binds once, so a re-scan never navigates twice", () => {
        const element = select(["/a", "/b"]);
        const navigate = vi.fn();

        bindNavSelect(element, navigate);
        bindNavSelect(element, navigate);
        element.dispatchEvent(new Event("change"));

        expect(navigate).toHaveBeenCalledTimes(1);
    });

    it("stays put when the option carries no URL", () => {
        const element = select([""]);
        const navigate = vi.fn();

        bindNavSelect(element, navigate);
        element.dispatchEvent(new Event("change"));

        expect(navigate).not.toHaveBeenCalled();
    });
});

describe("bindNavSelects", () => {
    it("binds every marked select and ignores the rest", () => {
        document.body.innerHTML = `
            <select data-nav-select><option value="/sorter" selected>x</option></select>
            <select data-nav-select><option value="/limiter" selected>x</option></select>
            <select><option value="/plain" selected>x</option></select>`;
        const navigate = vi.fn();

        bindNavSelects(document, navigate);
        document
            .querySelectorAll("select")
            .forEach((element) => element.dispatchEvent(new Event("change")));

        expect(navigate.mock.calls.flat()).toEqual(["/sorter", "/limiter"]);
    });
});
