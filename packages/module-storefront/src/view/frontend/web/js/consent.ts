// This file is part of the MageObsidian - Storefront project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
export interface Consent {
    isCookieRestrictionModeEnabled: boolean;
    currentWebsite: number;
    cookieName: string;
}

const cookie = (name: string, doc: Document): string | null => {
    const match = doc.cookie.match(new RegExp(`(?:^|; )${name.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}=([^;]*)`));
    return match ? decodeURIComponent(match[1]) : null;
};

/**
 * Magento's rule, not one of our own: with cookie restriction on, nothing is
 * reported until the shopper has accepted for this website.
 */
export function hasConsent(config: Consent, doc: Document = document): boolean {
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
