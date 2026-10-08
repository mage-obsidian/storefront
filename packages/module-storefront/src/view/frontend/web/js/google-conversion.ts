// This file is part of the MageObsidian - Storefront project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { hasConsent, type Consent } from "MageObsidian_Storefront::js/consent";

export interface ConversionConfig extends Consent {
    src: string;
    variables: Record<string, string | number>;
}

const SELECTOR = "[data-google-conversion]";

export function startConversion(root: ParentNode = document, view: Window = window): void {
    const doc = (root as Document).defaultView?.document ?? document;

    root.querySelectorAll<HTMLElement>(SELECTOR).forEach((element) => {
        let config: ConversionConfig;
        try {
            config = JSON.parse(element.getAttribute("data-google-conversion") ?? "") as ConversionConfig;
        } catch {
            return;
        }
        if (!hasConsent(config, doc)) {
            return;
        }
        if (Array.from(doc.querySelectorAll("script")).some((script) => script.getAttribute("src") === config.src)) {
            return;
        }

        Object.assign(view, config.variables);
        const script = doc.createElement("script");
        script.src = config.src;
        doc.head.appendChild(script);
    });
}

startConversion();
