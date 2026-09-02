import { beforeEach, describe, expect, it } from "vitest";
import { createFragmentStyleAdopter, declaredValues } from "./fragment-styles.ts";

const SWATCHES = ".ln__swatch-color--000000{background-color: #000000}";
const NAMES = ".product-item--1594{view-transition-name: product-1594}";

const section = (name: string, css: string | null): HTMLElement => {
    const holder = document.createElement("div");
    holder.dataset.obsidianSection = name;
    holder.innerHTML = css === null ? "<span></span>" : `<style>${css}</style><span></span>`;
    document.body.append(holder);

    return holder;
};

const adopted = (): string[] =>
    document.adoptedStyleSheets.flatMap((sheet) =>
        Array.from(sheet.cssRules).map((rule) => rule.cssText),
    );

describe("createFragmentStyleAdopter", () => {
    beforeEach(() => {
        document.body.innerHTML = "";
        document.adoptedStyleSheets = [];
    });

    it("republishes an incoming style block through the CSSOM", () => {
        const adopter = createFragmentStyleAdopter(document);

        expect(adopter.adopt(section("filters", SWATCHES), "filters")).toBe(1);
        expect(document.adoptedStyleSheets).toHaveLength(1);
        expect(adopted().join("")).toContain("ln__swatch-color--000000");
    });

    it("takes the style element out of the document so nothing rests on style-src", () => {
        const holder = section("filters", SWATCHES);
        createFragmentStyleAdopter(document).adopt(holder, "filters");

        expect(holder.querySelector("style")).toBeNull();
        expect(document.querySelectorAll("style")).toHaveLength(0);
    });

    it("joins every block a section carries", () => {
        const holder = section("listing", SWATCHES);
        const second = document.createElement("style");
        second.textContent = NAMES;
        holder.append(second);

        expect(createFragmentStyleAdopter(document).adopt(holder, "listing")).toBe(2);
        expect(adopted()).toHaveLength(2);
    });

    it("replaces what the previous swap left instead of stacking sheets", () => {
        const adopter = createFragmentStyleAdopter(document);
        adopter.adopt(section("filters", SWATCHES), "filters");

        document.body.innerHTML = "";
        adopter.adopt(section("filters", NAMES), "filters");

        expect(document.adoptedStyleSheets).toHaveLength(1);
        expect(adopted()).toHaveLength(1);
        expect(adopted().join("")).toContain("product-item--1594");
    });

    it("gives each section its own sheet", () => {
        const adopter = createFragmentStyleAdopter(document);
        adopter.adopt(section("filters", SWATCHES), "filters");
        adopter.adopt(section("listing", NAMES), "listing");

        expect(document.adoptedStyleSheets).toHaveLength(2);
        expect(adopted()).toHaveLength(2);
    });

    it("empties the sheet when a swap arrives without the rules it had", () => {
        const adopter = createFragmentStyleAdopter(document);
        adopter.adopt(section("filters", SWATCHES), "filters");

        document.body.innerHTML = "";
        expect(adopter.adopt(section("filters", null), "filters")).toBe(0);
        expect(adopted()).toHaveLength(0);
    });

    it("stays out of the way for a section that never carried styles", () => {
        const adopter = createFragmentStyleAdopter(document);

        expect(adopter.adopt(section("listing", null), "listing")).toBe(0);
        expect(document.adoptedStyleSheets).toHaveLength(0);
    });

    it("leaves the fragment alone where the document cannot adopt sheets", () => {
        const holder = section("filters", SWATCHES);
        const legacy = { querySelectorAll: document.querySelectorAll.bind(document) } as unknown as Document;

        expect(createFragmentStyleAdopter(legacy).adopt(holder, "filters")).toBe(0);
        expect(holder.querySelector("style")).not.toBeNull();
    });
});

describe("declaredValues", () => {
    const NAME = "view-transition-name";

    it("reads every value a stylesheet declares for the property", () => {
        const css =
            ".product-item--1{view-transition-name: product-1}" +
            ".product-item--2{view-transition-name: product-2; view-transition-class: obsidian-card}";

        expect(declaredValues(css, NAME)).toEqual(new Set(["product-1", "product-2"]));
    });

    it("ignores rules that declare nothing for it", () => {
        const css = ".a{color:red}.b{view-transition-name: kept}";

        expect(declaredValues(css, NAME)).toEqual(new Set(["kept"]));
    });

    it("treats an explicit none as no name at all", () => {
        expect(declaredValues(".a{view-transition-name: none}", NAME)).toEqual(new Set());
    });

    it("collapses a value two selectors declare", () => {
        const css = ".a{view-transition-name: same}.b{view-transition-name: same}";

        expect(declaredValues(css, NAME).size).toBe(1);
    });

    it("answers empty for css it cannot parse as rules", () => {
        expect(declaredValues("", NAME)).toEqual(new Set());
        expect(declaredValues("not css at all {{{", NAME)).toEqual(new Set());
    });
});
