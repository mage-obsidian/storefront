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
    firstname: string;
    lastname: string;
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
    company?: string;
    email?: string;
    save_in_address_book?: number;
}

/** A blank address, optionally pre-seeded with the store's default country. */
export function emptyAddress(countryId = ''): AddressData {
    return {
        firstname: '',
        lastname: '',
        company: '',
        street: ['', ''],
        city: '',
        region: '',
        regionId: null,
        postcode: '',
        countryId,
        telephone: '',
    };
}

/**
 * The names of the required fields missing a value, in form order. `region` is
 * required only when the selected country mandates a state. Used by the form to
 * mark fields and by the store to gate REST submission.
 */
export function missingFields(address: AddressData, regionRequired: boolean): string[] {
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
    if (!address.postcode.trim()) {
        missing.push('postcode');
    }
    if (!address.countryId.trim()) {
        missing.push('countryId');
    }
    if (regionRequired && !address.region.trim() && !address.regionId) {
        missing.push('region');
    }
    if (!address.telephone.trim()) {
        missing.push('telephone');
    }
    return missing;
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
