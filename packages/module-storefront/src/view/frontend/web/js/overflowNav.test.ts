import { describe, it, expect } from "vitest";
import { computeVisibleCount } from "MageObsidian_Storefront::js/overflowNav";

describe("computeVisibleCount", () => {
    it("keeps every item when they all fit (no More trigger)", () => {
        expect(computeVisibleCount([50, 50, 50], 10, 40, 1000)).toBe(3);
    });

    it("keeps every item at the exact fit boundary", () => {
        // 100 + 10 + 100 = 210 === container, no More needed.
        expect(computeVisibleCount([100, 100], 10, 40, 210)).toBe(2);
    });

    it("reserves room for the More trigger once it overflows", () => {
        // total 430 > 250; budget = 250 - 50 - 10 = 190. Only the first item fits.
        expect(computeVisibleCount([100, 100, 100, 100], 10, 50, 250)).toBe(1);
    });

    it("returns 0 when not even one item plus More fits (all go to the dropdown)", () => {
        expect(computeVisibleCount([500], 10, 50, 100)).toBe(0);
    });

    it("drops a single item once the More trigger no longer leaves room", () => {
        // Without More both fit (200 === 200); one px tighter forces the split.
        expect(computeVisibleCount([100, 100], 0, 50, 200)).toBe(2);
        expect(computeVisibleCount([100, 100], 0, 50, 199)).toBe(1);
    });

    it("handles an empty list", () => {
        expect(computeVisibleCount([], 10, 40, 500)).toBe(0);
    });
});

describe("subpixel widths", () => {
    it("keeps a bar that fits by a fraction of a pixel", () => {
        // Real measurement from the demo header: six items summing 529.47 in a
        // 529.47 bar. Rounding each to offsetWidth read it as 530 in 529.
        const items = [89.84, 60.92, 42.95, 51.94, 87.88, 35.94];
        expect(computeVisibleCount(items, 32, 45, 529.47)).toBe(6);
        expect(computeVisibleCount(items.map(Math.ceil), 32, 45, 529)).toBeLessThan(6);
    });

    it("still collapses on a real overflow, not just a rounding one", () => {
        expect(computeVisibleCount([200.4, 200.4, 200.4], 10, 50, 529.47)).toBe(2);
    });
});
