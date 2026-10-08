// This file is part of the MageObsidian - Catalog project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import events from "MageObsidian_ModernFrontend::js/events";
import { getFormKey } from "MageObsidian_Storefront::js/form-key-provider";
import { readConfig, record, syncRecord } from "MageObsidian_Catalog::js/recently";

const COMPARE_ADDED = "compare_add_after";

export interface CompareAdded {
    body: FormData;
    result?: boolean;
}

export interface CompareOptions {
    now?: number;
    formKey?: string;
    request?: (url: string, init: RequestInit) => Promise<Response>;
}

export async function onCompareAdded(added: CompareAdded, options: CompareOptions = {}): Promise<void> {
    const productId = String(added.body.get("product") ?? "");
    const config = readConfig();
    if (!added.result || !productId || !config) {
        return;
    }
    record("compared", productId, config, { now: options.now });
    await syncRecord("compared", productId, config, {
        now: options.now,
        formKey: options.formKey ?? getFormKey(),
        request: options.request,
    });
}

events.observe<CompareAdded>(COMPARE_ADDED, (added) => onCompareAdded(added));
