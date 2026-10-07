// This file is part of the MageObsidian - Customer project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { vi } from "vitest";
import { resetSiteConfig } from "mage-obsidian/runtime/cookies.ts";

export const NO_COOKIES_URL = "https://shop.test/cookie/index/noCookies/";

export function withCookiesOff(): { visited: string[]; restore: () => void } {
    document.head.innerHTML = `<script type="application/json" id="mage-obsidian-site-config">${JSON.stringify({
        version: 1,
        cookie: { domain: "", path: "/", lifetime: 3600, secure: false, sameSite: "lax" },
        websiteId: 1,
        noCookiesUrl: NO_COOKIES_URL,
    })}</script>`;
    resetSiteConfig();

    const dropped = vi.spyOn(document, "cookie", "set").mockImplementation(() => {});
    const original = window.location;
    const visited: string[] = [];
    Object.defineProperty(window, "location", {
        value: { assign: (url: string) => visited.push(url), href: "https://shop.test/", pathname: "/" },
        writable: true,
        configurable: true,
    });

    return {
        visited,
        restore: () => {
            dropped.mockRestore();
            Object.defineProperty(window, "location", { value: original, writable: true, configurable: true });
            document.head.innerHTML = "";
            resetSiteConfig();
        },
    };
}
