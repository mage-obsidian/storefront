// This file is part of the MageObsidian - Customer project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
// Progressive enhancement for the address form. The form is fully usable without
// JS (the region select is rendered server-side for the current country); this
// layer swaps the region control when the country changes and wires accessible
// inline validation. The region requiredness is conditional, so it rides on a
// closure flag the country handler keeps in sync.

import { enhanceValidation, required, when, type FieldRules } from "MageObsidian_Storefront::js/form-validation";

interface RegionEntry {
    id: number | string;
    name: string;
}

interface RegionConfig {
    show_all_regions?: boolean;
    regions_required?: string[];
}

export interface RegionState {
    mode: "select" | "text";
    required: boolean;
    regions: RegionEntry[];
}

/**
 * Decide how the region control should behave for a country: a populated select
 * when the country has regions, a free-text input otherwise; required when the
 * country is in the directory's regions_required list.
 */
export function computeRegionState(regionJson: Record<string, unknown>, country: string): RegionState {
    const config = (regionJson.config ?? {}) as RegionConfig;
    const requiredList = config.regions_required ?? [];
    const entry = regionJson[country];
    const regions: RegionEntry[] = Array.isArray(entry) ? (entry as RegionEntry[]) : [];

    return {
        mode: regions.length > 0 ? "select" : "text",
        required: requiredList.includes(country),
        regions,
    };
}

function parseRegionJson(raw: string): Record<string, unknown> {
    try {
        const parsed = JSON.parse(raw);
        return typeof parsed === "object" && parsed !== null ? parsed : {};
    } catch {
        return {};
    }
}

function applyRegionState(select: HTMLSelectElement, input: HTMLInputElement, state: RegionState): void {
    if (state.mode === "select") {
        const placeholder = select.options[0]?.cloneNode(true) ?? document.createElement("option");
        select.replaceChildren(placeholder);
        for (const region of state.regions) {
            const option = document.createElement("option");
            option.value = String(region.id);
            option.textContent = region.name;
            select.add(option);
        }
        select.disabled = false;
        select.classList.remove("hidden");
        input.disabled = true;
        input.classList.add("hidden");
    } else {
        select.disabled = true;
        select.classList.add("hidden");
        input.disabled = false;
        input.classList.remove("hidden");
    }
}

function parseOptionalZip(raw: string): string[] {
    try {
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed.map(String) : [];
    } catch {
        return [];
    }
}

function markRequired(form: HTMLFormElement, control: HTMLInputElement, isRequired: boolean): void {
    control.required = isRequired;
    if (isRequired) {
        control.setAttribute("aria-required", "true");
    } else {
        control.removeAttribute("aria-required");
        control.removeAttribute("aria-invalid");
    }

    const label = control.id ? form.querySelector(`label[for="${control.id}"]`) : null;
    const marker = label?.querySelector(".field__required") ?? null;
    if (isRequired && label && !marker) {
        const star = document.createElement("span");
        star.className = "field__required";
        star.setAttribute("aria-hidden", "true");
        star.textContent = "*";
        label.append(star);
    } else if (!isRequired && marker) {
        marker.remove();
    }
}

export function enhanceAddressForm(form: HTMLFormElement): void {
    const country = form.querySelector<HTMLSelectElement>("[data-country]");
    const regionSelect = form.querySelector<HTMLSelectElement>("[data-region-select]");
    const regionInput = form.querySelector<HTMLInputElement>("[data-region-input]");
    if (!country || !regionSelect || !regionInput) {
        return;
    }

    const regionJson = parseRegionJson(form.querySelector("[data-region-json]")?.textContent ?? "");
    const requiredMessage = form.dataset.errRequired ?? "This is a required field.";

    // The country handler keeps this in sync; the validation predicate reads it.
    let regionRequired = computeRegionState(regionJson, country.value).required
        && regionSelect.classList.contains("hidden") === false;

    const syncRegion = (): void => {
        const state = computeRegionState(regionJson, country.value);
        applyRegionState(regionSelect, regionInput, state);
        regionRequired = state.mode === "select" && state.required;
    };

    country.addEventListener("change", syncRegion);

    const optionalZip = parseOptionalZip(form.dataset.optionalZip ?? "");
    const postcode = form.querySelector<HTMLInputElement>('[name="postcode"]');
    let postcodeRequired = !optionalZip.includes(country.value);

    const syncPostcode = (): void => {
        postcodeRequired = !optionalZip.includes(country.value);
        if (postcode) {
            markRequired(form, postcode, postcodeRequired);
        }
    };

    country.addEventListener("change", syncPostcode);
    syncPostcode();

    const rules: FieldRules = {
        region_id: [when(() => regionRequired, required(requiredMessage))],
    };

    for (const control of Array.from(form.elements)) {
        const name = (control as HTMLInputElement).name;
        if (name === "" || name === "postcode" || name in rules || !(control as HTMLInputElement).required) {
            continue;
        }
        rules[name] = [required(requiredMessage)];
    }

    if (postcode) {
        rules.postcode = [when(() => postcodeRequired, required(requiredMessage))];
    }

    enhanceValidation(form, rules);
}

const form = document.querySelector<HTMLFormElement>("[data-address-form]");
if (form) {
    enhanceAddressForm(form);
}
