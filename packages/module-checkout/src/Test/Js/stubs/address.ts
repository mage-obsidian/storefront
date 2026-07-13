// Test stub for MageObsidian_Storefront::js/address, aliased in vitest.config.js.
// Mirrors the surface the checkout store uses (the address shape, a blank-address
// factory and the REST mapping) so the store tests run in isolation without the
// sibling repo. The real, fully-tested helpers live in module-storefront; the
// assembled vue-tsc gate catches any drift between this surface and the real one.
export interface AddressData {
    firstname: string;
    lastname: string;
    company: string;
    street: string[];
    city: string;
    region: string;
    regionId: number | null;
    postcode: string;
    countryId: string;
    telephone: string;
}

export interface RestAddress {
    country_id: string;
    region: string;
    region_id: number | null;
    street: string[];
    city: string;
    postcode: string;
    telephone: string;
    firstname: string;
    lastname: string;
    company?: string;
    email?: string;
}

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

export function toRestAddress(address: AddressData, extra: { email?: string } = {}): RestAddress {
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
    if (extra.email) {
        rest.email = extra.email;
    }
    return rest;
}
