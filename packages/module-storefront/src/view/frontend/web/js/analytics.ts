/**
 * Google Analytics 4, over the platform's own configuration and the platform's
 * own consent rule.
 *
 * The data comes from Magento's Ga block unchanged — measurement id, cookie
 * restriction, and on the success page the order it just placed. What this adds
 * is the deferral: gtag is a third-party script and is held until the shopper
 * interacts, except where the page declares otherwise. The success page is the
 * one that declares otherwise, because a purchase that waits for an interaction
 * is a purchase that is never reported.
 */
import { whenReady, type DeferUntil } from "MageObsidian_Storefront::js/deferred-scripts";

export interface OrderTracking {
    transaction_id: string;
    value: number;
    tax: number;
    shipping: number;
    currency: string;
    items?: unknown[];
}

export interface AnalyticsConfig {
    isCookieRestrictionModeEnabled: boolean;
    currentWebsite: number;
    cookieName: string;
    pageTrackingData: { measurementId: string; optPageUrl?: string };
    ordersTrackingData: { orders?: OrderTracking[]; products?: unknown[] };
    googleAnalyticsAvailable: boolean;
}

type Gtag = (...args: unknown[]) => void;
type Holder = Window & { dataLayer?: unknown[]; gtag?: Gtag };

const SELECTOR = "[data-analytics]";
const LIBRARY = "https://www.googletagmanager.com/gtag/js?id=";

const cookie = (name: string, doc: Document): string | null => {
    const match = doc.cookie.match(new RegExp(`(?:^|; )${name.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}=([^;]*)`));
    return match ? decodeURIComponent(match[1]) : null;
};

/**
 * Magento's rule, not one of our own: with cookie restriction on, nothing is
 * reported until the shopper has accepted for this website.
 */
export function mayReport(config: AnalyticsConfig, doc: Document = document): boolean {
    if (!config.googleAnalyticsAvailable || !config.pageTrackingData?.measurementId) {
        return false;
    }
    if (!config.isCookieRestrictionModeEnabled) {
        return true;
    }

    const accepted = cookie(config.cookieName, doc);
    if (!accepted) {
        return false;
    }
    try {
        return (JSON.parse(accepted) as Record<string, number>)[config.currentWebsite] === 1;
    } catch {
        return false;
    }
}

export function report(config: AnalyticsConfig, view: Window = window): void {
    const holder = view as Holder;
    holder.dataLayer = holder.dataLayer ?? [];
    const gtag: Gtag =
        holder.gtag ??
        function (...args: unknown[]) {
            holder.dataLayer?.push(args);
        };
    holder.gtag = gtag;

    gtag("js", new Date());
    gtag("config", config.pageTrackingData.measurementId, { anonymize_ip: true });

    const order = config.ordersTrackingData?.orders?.[0];
    if (order) {
        gtag("event", "purchase", { ...order, items: config.ordersTrackingData.products ?? [] });
    }
}

export function startAnalytics(root: ParentNode = document, view: Window = window): void {
    const doc = (root as Document).defaultView?.document ?? document;

    root.querySelectorAll<HTMLElement>(SELECTOR).forEach((element) => {
        let config: AnalyticsConfig;
        try {
            config = JSON.parse(element.getAttribute("data-analytics") ?? "") as AnalyticsConfig;
        } catch {
            return;
        }
        if (!mayReport(config, doc)) {
            return;
        }

        // The queue is filled first and the library is fetched on the deferral
        // mechanism's schedule: what is queued before it lands is drained the
        // moment it does, so nothing is lost between the shopper's first move
        // and the script arriving.
        report(config, view);

        const until = (element.getAttribute("data-until") as DeferUntil | null) ?? "interaction";
        whenReady(
            until,
            () => {
                if (doc.querySelector(`script[src^="${LIBRARY}"]`)) {
                    return;
                }
                const script = doc.createElement("script");
                script.async = true;
                script.src = LIBRARY + encodeURIComponent(config.pageTrackingData.measurementId);
                doc.head.appendChild(script);
            },
            view,
        );
    });
}

startAnalytics();
