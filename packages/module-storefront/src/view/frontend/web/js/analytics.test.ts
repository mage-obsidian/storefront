import { beforeEach, describe, expect, it } from "vitest";
import { mayReport, report, startAnalytics, type AnalyticsConfig } from "./analytics.ts";

const CONFIG: AnalyticsConfig = {
    isCookieRestrictionModeEnabled: false,
    currentWebsite: 1,
    cookieName: "user_allowed_save_cookie",
    pageTrackingData: { measurementId: "G-OBSIDIAN" },
    ordersTrackingData: {},
    googleAnalyticsAvailable: true,
};

const declare = (config: Partial<AnalyticsConfig>, attributes = ""): void => {
    const payload = JSON.stringify({ ...CONFIG, ...config }).replace(/"/g, "&quot;");
    document.body.innerHTML = `<div data-analytics="${payload}" data-deferred-script data-src="https://tag.test/gtag.js" ${attributes} hidden></div>`;
};

const holder = (): Window & { dataLayer?: unknown[]; gtag?: unknown } =>
    window as Window & { dataLayer?: unknown[]; gtag?: unknown };

beforeEach(() => {
    document.body.innerHTML = "";
    document.head.querySelectorAll("script").forEach((script) => script.remove());
    delete holder().dataLayer;
    delete holder().gtag;
    document.cookie = "user_allowed_save_cookie=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
});

describe("mayReport", () => {
    it("reports when the store asks for no consent", () => {
        expect(mayReport(CONFIG)).toBe(true);
    });

    // The platform's rule, not one of our own.
    it("waits for consent when the store restricts cookies", () => {
        expect(mayReport({ ...CONFIG, isCookieRestrictionModeEnabled: true })).toBe(false);
    });

    it("reports once this website has been accepted", () => {
        document.cookie = `user_allowed_save_cookie=${encodeURIComponent('{"1":1}')}; path=/`;

        expect(mayReport({ ...CONFIG, isCookieRestrictionModeEnabled: true })).toBe(true);
    });

    it("does not take another website's consent for this one", () => {
        document.cookie = `user_allowed_save_cookie=${encodeURIComponent('{"2":1}')}; path=/`;

        expect(mayReport({ ...CONFIG, isCookieRestrictionModeEnabled: true })).toBe(false);
    });

    it("says nothing without a measurement id", () => {
        expect(mayReport({ ...CONFIG, pageTrackingData: { measurementId: "" } })).toBe(false);
    });

    it("says nothing when the platform reports the integration unavailable", () => {
        expect(mayReport({ ...CONFIG, googleAnalyticsAvailable: false })).toBe(false);
    });
});

describe("report", () => {
    it("queues the page view so the library drains it when it lands", () => {
        report(CONFIG, window);

        expect(holder().dataLayer).toEqual([
            expect.arrayContaining(["js"]),
            ["config", "G-OBSIDIAN", { anonymize_ip: true }],
        ]);
    });

    /**
     * A purchase reported without its lines is a number nobody can reconcile,
     * and it is the one event a store cannot re-derive later.
     */
    it("reports the purchase with the order and its lines", () => {
        report(
            {
                ...CONFIG,
                ordersTrackingData: {
                    orders: [{ transaction_id: "000000123", value: 99.5, tax: 8, shipping: 5, currency: "USD" }],
                    products: [{ item_id: "SKU-1", quantity: 2 }],
                },
            },
            window,
        );

        const purchase = (holder().dataLayer ?? []).find(
            (entry) => Array.isArray(entry) && entry[0] === "event" && entry[1] === "purchase",
        ) as unknown[];
        expect(purchase[2]).toMatchObject({ transaction_id: "000000123", value: 99.5, items: [{ item_id: "SKU-1" }] });
    });

    it("reports no purchase on a page that placed no order", () => {
        report(CONFIG, window);

        expect((holder().dataLayer ?? []).some((entry) => Array.isArray(entry) && entry[1] === "purchase")).toBe(false);
    });
});

describe("startAnalytics", () => {
    it("reads the platform's own payload off the page", () => {
        declare({});

        startAnalytics(document, window);

        expect(holder().dataLayer).toBeDefined();
    });

    it("reports nothing at all when consent is missing", () => {
        declare({ isCookieRestrictionModeEnabled: true });

        startAnalytics(document, window);

        expect(holder().dataLayer).toBeUndefined();
    });

    /**
     * The point of the pairing: the library itself is third-party and waits, so
     * a page that nobody touches never pays for it.
     */
    it("fetches the library only once the shopper has done something", () => {
        declare({});

        startAnalytics(document, window);
        expect(document.head.querySelectorAll('script[src*="googletagmanager"]')).toHaveLength(0);

        window.dispatchEvent(new Event("pointerdown"));

        const script = document.head.querySelector('script[src*="googletagmanager"]');
        expect(script?.getAttribute("src")).toContain("id=G-OBSIDIAN");
    });

    // A purchase that waits for an interaction is a purchase that is never
    // reported: the success page declares the bypass.
    it("fetches it straight away where the page declares a bypass", () => {
        declare({}, 'data-until="now" data-reason="a purchase that waits is never reported"');

        startAnalytics(document, window);

        expect(document.head.querySelectorAll('script[src*="googletagmanager"]')).toHaveLength(1);
    });

    it("survives a payload it cannot read", () => {
        document.body.innerHTML = '<div data-analytics="{oops"></div>';

        expect(() => startAnalytics(document, window)).not.toThrow();
    });
});
