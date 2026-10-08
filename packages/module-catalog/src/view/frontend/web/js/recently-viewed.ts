// This file is part of the MageObsidian - Catalog project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { getFormKey } from "MageObsidian_Storefront::js/form-key-provider";
import { readConfig, record, syncRecord } from "MageObsidian_Catalog::js/recently";

const MARKER = "[data-recently-viewed]";

export interface ViewOptions {
    now?: number;
    formKey?: string;
    request?: (url: string, init: RequestInit) => Promise<Response>;
}

export async function recordView(options: ViewOptions = {}): Promise<void> {
    const productId = document.querySelector<HTMLElement>(MARKER)?.dataset.recentlyViewed;
    const config = readConfig();
    if (!productId || !config) {
        return;
    }
    record("viewed", productId, config, { now: options.now });
    await syncRecord("viewed", productId, config, {
        now: options.now,
        formKey: options.formKey ?? getFormKey(),
        request: options.request,
    });
}

void recordView();
