// This file is part of the MageObsidian - Storefront project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
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
import { hasConsent } from "MageObsidian_Storefront::js/consent";
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

export interface AdsConversion {
    sendTo: string;
    value: number;
    currency: string;
    transactionId: string;
}

export interface AdsConfig {
    isCookieRestrictionModeEnabled: boolean;
    currentWebsite: number;
    cookieName: string;
    conversionId: string;
    src: string;
    conversion?: AdsConversion;
}

type Gtag = (...args: unknown[]) => void;
type Holder = Window & { dataLayer?: unknown[]; gtag?: Gtag };

const SELECTOR = "[data-analytics]";
const ADS_SELECTOR = "[data-google-ads]";
const LIBRARY = "https://www.googletagmanager.com/gtag/js?id=";

export function mayReport(config: AnalyticsConfig, doc: Document = document): boolean {
    if (!config.googleAnalyticsAvailable || !config.pageTrackingData?.measurementId) {
        return false;
    }
    return hasConsent(config, doc);
}

export function mayAdvertise(config: AdsConfig, doc: Document = document): boolean {
    return Boolean(config.conversionId) && hasConsent(config, doc);
}

function queueOf(view: Window): Gtag {
    const holder = view as Holder;
    holder.dataLayer = holder.dataLayer ?? [];
    const gtag: Gtag =
        holder.gtag ??
        function (...args: unknown[]) {
            holder.dataLayer?.push(args);
        };
    holder.gtag = gtag;
    return gtag;
}

function startQueue(gtag: Gtag, view: Window): void {
    const started = ((view as Holder).dataLayer ?? []).some((entry) => (entry as ArrayLike<unknown>)[0] === "js");
    if (!started) {
        gtag("js", new Date());
    }
}

function fetchLibrary(doc: Document, src: string): void {
    if (Array.from(doc.querySelectorAll("script")).some((script) => script.getAttribute("src") === src)) {
        return;
    }
    const script = doc.createElement("script");
    script.async = true;
    script.src = src;
    doc.head.appendChild(script);
}

export function advertise(config: AdsConfig, view: Window = window): void {
    const gtag = queueOf(view);
    startQueue(gtag, view);
    gtag("config", config.conversionId);

    if (config.conversion) {
        gtag("event", "conversion", {
            send_to: config.conversion.sendTo,
            value: config.conversion.value,
            currency: config.conversion.currency,
            transaction_id: config.conversion.transactionId,
        });
    }
}

export function report(config: AnalyticsConfig, view: Window = window): void {
    const gtag = queueOf(view);
    startQueue(gtag, view);
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
            () => fetchLibrary(doc, LIBRARY + encodeURIComponent(config.pageTrackingData.measurementId)),
            view,
        );
    });

    root.querySelectorAll<HTMLElement>(ADS_SELECTOR).forEach((element) => {
        let config: AdsConfig;
        try {
            config = JSON.parse(element.getAttribute("data-google-ads") ?? "") as AdsConfig;
        } catch {
            return;
        }
        if (!mayAdvertise(config, doc)) {
            return;
        }

        advertise(config, view);

        const until = (element.getAttribute("data-until") as DeferUntil | null) ?? "interaction";
        whenReady(until, () => fetchLibrary(doc, config.src), view);
    });
}

startAnalytics();
