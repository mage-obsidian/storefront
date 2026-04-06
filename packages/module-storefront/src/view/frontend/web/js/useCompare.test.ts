import { describe, it, expect, beforeEach, vi } from "vitest";
import { useCompare } from "./useCompare.ts";
import { __setSection, __reset, reload } from "MageObsidian_ModernFrontend::js/customer-data";

function mockFetch(ok = true) {
    globalThis.fetch = vi.fn().mockResolvedValue({ ok, status: ok ? 200 : 400 });
    return globalThis.fetch as ReturnType<typeof vi.fn>;
}

const REMOVE = JSON.stringify({
    action: "https://shop.test/catalog/product_compare/remove/",
    data: { product: 12, uenc: "abc" },
});

beforeEach(() => {
    __reset();
    document.cookie = "form_key=abc123";
});

describe("useCompare", () => {
    it("reads count and membership from the compare-products section", () => {
        __setSection("compare-products", { items: [{ id: 12, remove_url: REMOVE }, { id: 34 }] });
        const compare = useCompare();

        expect(compare.count.value).toBe(2);
        expect(compare.has(12)).toBe(true);
        expect(compare.has("34")).toBe(true);
        expect(compare.has(99)).toBe(false);
    });

    it("adds from a form and reloads the section", async () => {
        const fetchMock = mockFetch();
        const form = document.createElement("form");
        form.action = "https://shop.test/catalog/product_compare/add/";

        const ok = await useCompare().add(form);

        expect(ok).toBe(true);
        expect(fetchMock.mock.calls[0][0]).toBe("https://shop.test/catalog/product_compare/add/");
        expect(reload.calls).toContainEqual([["compare-products"]]);
    });

    it("removes by parsing the item's post-data remove_url", async () => {
        const fetchMock = mockFetch();
        __setSection("compare-products", { items: [{ id: 12, remove_url: REMOVE }] });

        const ok = await useCompare().remove(12);

        expect(ok).toBe(true);
        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe("https://shop.test/catalog/product_compare/remove/");
        expect((init.body as FormData).get("product")).toBe("12");
        expect((init.body as FormData).get("uenc")).toBe("abc");
    });

    it("does not POST when removing a product not in the list", async () => {
        const fetchMock = mockFetch();
        const ok = await useCompare().remove(999);

        expect(ok).toBe(false);
        expect(fetchMock).not.toHaveBeenCalled();
    });
});
