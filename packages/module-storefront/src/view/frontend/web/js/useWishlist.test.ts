import { describe, it, expect, beforeEach, vi } from "vitest";
import { useWishlist } from "./useWishlist.ts";
import { __setSection, __reset, reload } from "MageObsidian_ModernFrontend::js/customer-data";

function mockFetch(ok = true) {
    globalThis.fetch = vi.fn().mockResolvedValue({ ok, status: ok ? 200 : 400 });
    return globalThis.fetch as ReturnType<typeof vi.fn>;
}

beforeEach(() => {
    __reset();
    document.cookie = "form_key=abc123";
});

describe("useWishlist", () => {
    it("reads count and membership from the wishlist section", () => {
        __setSection("wishlist", { saved: { "12": "/wishlist/index/remove/item/5/", "34": "/wishlist/index/remove/item/9/" } });
        const wishlist = useWishlist();

        expect(wishlist.count.value).toBe(2);
        expect(wishlist.has(12)).toBe(true);
        expect(wishlist.has("34")).toBe(true);
        expect(wishlist.has(99)).toBe(false);
    });

    it("reports the logged-in state from the customer section", () => {
        const wishlist = useWishlist();
        expect(wishlist.isLoggedIn.value).toBe(false);
        __setSection("customer", { firstname: "Ada" });
        expect(wishlist.isLoggedIn.value).toBe(true);
    });

    it("adds from a form and reloads the wishlist section", async () => {
        const fetchMock = mockFetch();
        const form = document.createElement("form");
        form.action = "https://shop.test/wishlist/index/add/";
        const ok = await useWishlist().add(form);

        expect(ok).toBe(true);
        expect(fetchMock.mock.calls[0][0]).toBe("https://shop.test/wishlist/index/add/");
        expect(reload.calls).toContainEqual([["wishlist"]]);
    });

    it("removes via the per-product url from the section", async () => {
        const fetchMock = mockFetch();
        __setSection("wishlist", { saved: { "12": "https://shop.test/wishlist/index/remove/item/5/" } });

        const ok = await useWishlist().remove(12);

        expect(ok).toBe(true);
        expect(fetchMock.mock.calls[0][0]).toBe("https://shop.test/wishlist/index/remove/item/5/");
    });

    it("does not POST when removing a product that is not saved", async () => {
        const fetchMock = mockFetch();
        const ok = await useWishlist().remove(999);

        expect(ok).toBe(false);
        expect(fetchMock).not.toHaveBeenCalled();
    });
});
