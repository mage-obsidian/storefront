import { describe, it, expect, vi } from "vitest";
import { readCookie, consentCookie, bindCookieNotice } from "./cookie-notice.ts";

function makeBanner(): HTMLElement {
    const el = document.createElement("div");
    el.setAttribute("data-cookie-notice", "");
    el.dataset.cookieName = "user_allowed_save_cookie";
    el.dataset.cookieValue = '{"1":1}';
    el.dataset.cookieLifetime = "3600";
    el.dataset.noCookiesUrl = "https://shop.test/cookie/index/noCookies";
    el.hidden = true;
    const btn = document.createElement("button");
    btn.setAttribute("data-cookie-allow", "");
    el.appendChild(btn);
    return el;
}

describe("cookie-notice helpers", () => {
    it("reads a URL-decoded cookie value or null", () => {
        const jar = 'foo=bar; user_allowed_save_cookie=%7B%221%22%3A1%7D; baz=qux';
        expect(readCookie("user_allowed_save_cookie", jar)).toBe('{"1":1}');
        expect(readCookie("missing", jar)).toBeNull();
    });

    it("serializes the consent cookie with path, max-age and samesite", () => {
        expect(consentCookie("user_allowed_save_cookie", '{"1":1}', 3600)).toBe(
            "user_allowed_save_cookie=%7B%221%22%3A1%7D; path=/; max-age=3600; samesite=lax",
        );
    });

    it("omits max-age when the lifetime is zero (session cookie)", () => {
        expect(consentCookie("c", "v", 0)).toBe("c=v; path=/; samesite=lax");
    });
});

describe("bindCookieNotice", () => {
    function fakeDoc(initialCookie = "") {
        let jar = initialCookie;
        return {
            cookie: "",
            dispatched: [] as string[],
            get __jar() {
                return jar;
            },
            // emulate document.cookie set/get
            _install(banner: HTMLElement) {
                const self = this;
                Object.defineProperty(self, "cookie", {
                    get: () => jar,
                    set: (entry: string) => {
                        const [pair] = entry.split(";");
                        jar = jar ? `${jar}; ${pair}` : pair;
                    },
                    configurable: true,
                });
                self.dispatchEvent = (e: Event) => {
                    self.dispatched.push(e.type);
                    return true;
                };
                self.querySelector = banner.querySelector.bind(banner);
            },
        } as unknown as Document & { dispatched: string[] };
    }

    it("stays hidden when consent was already given", () => {
        const banner = makeBanner();
        const doc = fakeDoc("user_allowed_save_cookie=%7B%221%22%3A1%7D") as any;
        doc._install(banner);
        bindCookieNotice(banner, doc);
        expect(banner.hidden).toBe(true);
    });

    it("reveals the banner when consent is missing", () => {
        const banner = makeBanner();
        const doc = fakeDoc("") as any;
        doc._install(banner);
        bindCookieNotice(banner, doc);
        expect(banner.hidden).toBe(false);
    });

    it("writes the consent cookie and hides the banner on allow", () => {
        const banner = makeBanner();
        const doc = fakeDoc("") as any;
        doc._install(banner);
        bindCookieNotice(banner, doc);

        banner.querySelector<HTMLButtonElement>("[data-cookie-allow]")!.click();

        expect(doc.cookie).toContain("user_allowed_save_cookie=%7B%221%22%3A1%7D");
        expect(banner.hidden).toBe(true);
        expect(doc.dispatched).toContain("user:allowed:save:cookie");
    });

    it("falls back to the no-cookies URL when the cookie cannot be stored", () => {
        const banner = makeBanner();
        const doc = fakeDoc("") as any;
        // a document whose cookie set is a no-op (browser refused)
        doc.querySelector = banner.querySelector.bind(banner);
        Object.defineProperty(doc, "cookie", { get: () => "", set: () => {}, configurable: true });
        doc.dispatchEvent = () => true;
        const hrefs: string[] = [];
        const original = window.location;
        Object.defineProperty(window, "location", { value: { ...original, set href(v: string) { hrefs.push(v); } }, configurable: true });

        bindCookieNotice(banner, doc);
        banner.querySelector<HTMLButtonElement>("[data-cookie-allow]")!.click();

        expect(hrefs).toEqual(["https://shop.test/cookie/index/noCookies"]);
        Object.defineProperty(window, "location", { value: original, configurable: true });
    });
});
