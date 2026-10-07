// This file is part of the MageObsidian - Storefront project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
/**
 * Address value type + pure helpers shared by every address island (checkout
 * shipping/billing today, the customer address book later). The foundation owns
 * the shape and the mapping to Magento's REST address envelope so the consumers
 * (the Vue forms, the checkout store) never re-declare it. Pure and Vue-free, so
 * the required-field rules are unit-tested without mounting a component.
 */

export interface RegionData {
    id: number;
    code: string;
    name: string;
}

export interface AddressData {
    prefix: string;
    firstname: string;
    middlename: string;
    lastname: string;
    suffix: string;
    company: string;
    /** Street lines; [0] is required, [1] optional. */
    street: string[];
    city: string;
    /** Free-text region name (mirrors the selected region or a typed one). */
    region: string;
    /** Directory region id when picked from the select; null for free text. */
    regionId: number | null;
    postcode: string;
    countryId: string;
    telephone: string;
    fax: string;
    vatId: string;
}

export interface FieldRule {
    visible: boolean;
    required: boolean;
    options: string[];
}

export interface AddressRules {
    fields: Record<string, FieldRule>;
    streetLines: number;
    optionalZipCountries: string[];
}

const hiddenRule = (): FieldRule => ({ visible: false, required: false, options: [] });

export function defaultAddressRules(): AddressRules {
    return {
        fields: {
            prefix: hiddenRule(),
            middlename: hiddenRule(),
            suffix: hiddenRule(),
            company: { visible: true, required: false, options: [] },
            telephone: { visible: true, required: true, options: [] },
            fax: hiddenRule(),
            vat_id: hiddenRule(),
        },
        streetLines: 2,
        optionalZipCountries: [],
    };
}

export function normalizeAddressRules(raw: unknown): AddressRules {
    const base = defaultAddressRules();
    const source = (typeof raw === 'object' && raw !== null ? raw : {}) as Partial<AddressRules>;
    const fields = { ...base.fields };
    for (const [code, rule] of Object.entries(source.fields ?? {})) {
        fields[code] = {
            visible: Boolean(rule?.visible),
            required: Boolean(rule?.visible) && Boolean(rule?.required),
            options: Array.isArray(rule?.options) ? rule.options.map(String) : [],
        };
    }
    const lines = Number(source.streetLines);
    return {
        fields,
        streetLines: lines >= 1 ? Math.min(lines, 20) : base.streetLines,
        optionalZipCountries: Array.isArray(source.optionalZipCountries) ? source.optionalZipCountries.map(String) : [],
    };
}

export function postcodeRequired(countryId: string, rules: AddressRules): boolean {
    return !rules.optionalZipCountries.includes(countryId);
}

/**
 * Magento's REST address envelope (snake_case), as the quote endpoints expect it.
 * Only the fields the one-page flow sets are typed.
 */
export interface RestAddress {
    country_id: string;
    region: string;
    region_id: number | null;
    region_code?: string;
    street: string[];
    city: string;
    postcode: string;
    telephone: string;
    firstname: string;
    lastname: string;
    prefix?: string;
    middlename?: string;
    suffix?: string;
    fax?: string;
    vat_id?: string;
    company?: string;
    email?: string;
    save_in_address_book?: number;
    customer_address_id?: number;
}

/** A blank address, optionally pre-seeded with the store's default country. */
export function emptyAddress(countryId = '', streetLines = 2): AddressData {
    return {
        prefix: '',
        firstname: '',
        middlename: '',
        lastname: '',
        suffix: '',
        company: '',
        street: Array.from({ length: Math.max(streetLines, 1) }, () => ''),
        city: '',
        region: '',
        regionId: null,
        postcode: '',
        countryId,
        telephone: '',
        fax: '',
        vatId: '',
    };
}

function isRequired(rules: AddressRules, code: string): boolean {
    const rule = rules.fields[code];
    return Boolean(rule?.visible && rule.required);
}

/**
 * The names of the required fields missing a value, in form order. `region` is
 * required only when the selected country mandates a state. Used by the form to
 * mark fields and by the store to gate REST submission.
 */
export function missingFields(
    address: AddressData,
    regionRequired: boolean,
    rules: AddressRules = defaultAddressRules(),
): string[] {
    const missing: string[] = [];
    if (!address.firstname.trim()) {
        missing.push('firstname');
    }
    if (!address.lastname.trim()) {
        missing.push('lastname');
    }
    if (!(address.street[0] ?? '').trim()) {
        missing.push('street0');
    }
    if (!address.city.trim()) {
        missing.push('city');
    }
    if (!address.postcode.trim() && postcodeRequired(address.countryId, rules)) {
        missing.push('postcode');
    }
    if (!address.countryId.trim()) {
        missing.push('countryId');
    }
    if (regionRequired && !address.region.trim() && !address.regionId) {
        missing.push('region');
    }
    if (!address.telephone.trim() && isRequired(rules, 'telephone')) {
        missing.push('telephone');
    }
    const optionalFields: Array<[string, string, string]> = [
        ['prefix', 'prefix', address.prefix],
        ['middlename', 'middlename', address.middlename],
        ['suffix', 'suffix', address.suffix],
        ['company', 'company', address.company],
        ['fax', 'fax', address.fax],
        ['vat_id', 'vatId', address.vatId],
    ];
    for (const [code, name, value] of optionalFields) {
        if (isRequired(rules, code) && !(value ?? '').trim()) {
            missing.push(name);
        }
    }
    return missing;
}

export const ADDRESS_FIELD_LABELS: Record<string, string> = {
    firstname: 'First name',
    lastname: 'Last name',
    prefix: 'Prefix',
    middlename: 'Middle name',
    suffix: 'Suffix',
    company: 'Company',
    fax: 'Fax',
    vatId: 'VAT number',
    street: 'Street address',
    city: 'City',
    country: 'Country',
    region: 'State / Province',
    postcode: 'ZIP / Postal code',
    telephone: 'Phone number',
};

const LABEL_KEY_OF: Record<string, string> = { street0: 'street', countryId: 'country' };

export function addressFieldLabel(field: string, labels: Record<string, string> = {}): string {
    const key = LABEL_KEY_OF[field] ?? field;

    return labels[key] ?? ADDRESS_FIELD_LABELS[key] ?? key;
}

/**
 * Map the form address to Magento's REST envelope, dropping empty street lines
 * and omitting the region id/code when the region is free text. `extra` carries
 * the guest email and the save-in-book flag when the caller needs them.
 */
export function toRestAddress(
    address: AddressData,
    extra: { email?: string; regionCode?: string; saveInAddressBook?: boolean } = {},
): RestAddress {
    const street = address.street.map((line) => line.trim()).filter((line) => line !== '');
    const rest: RestAddress = {
        country_id: address.countryId,
        region: address.region,
        region_id: address.regionId,
        street: street.length > 0 ? street : [''],
        city: address.city,
        postcode: address.postcode,
        telephone: address.telephone,
        firstname: address.firstname,
        lastname: address.lastname,
    };
    if (address.company.trim() !== '') {
        rest.company = address.company;
    }
    const optionalFields: Array<[keyof RestAddress, string]> = [
        ['prefix', address.prefix],
        ['middlename', address.middlename],
        ['suffix', address.suffix],
        ['fax', address.fax],
        ['vat_id', address.vatId],
    ];
    for (const [key, value] of optionalFields) {
        if ((value ?? '').trim() !== '') {
            (rest as Record<string, unknown>)[key] = value;
        }
    }
    if (extra.regionCode) {
        rest.region_code = extra.regionCode;
    }
    if (extra.email) {
        rest.email = extra.email;
    }
    if (extra.saveInAddressBook !== undefined) {
        rest.save_in_address_book = extra.saveInAddressBook ? 1 : 0;
    }
    return rest;
}
