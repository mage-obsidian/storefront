// This file is part of the MageObsidian - Storefront project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect } from "vitest";
import {
    addressFieldLabel,
    defaultAddressRules,
    emptyAddress,
    missingFields,
    postcodeRequired,
    toRestAddress,
    type AddressData,
    type AddressRules,
} from "./address.ts";

function filled(overrides: Partial<AddressData> = {}): AddressData {
    return {
        ...emptyAddress("US"),
        firstname: "Ada",
        lastname: "Lovelace",
        street: ["1 Analytical Way", ""],
        city: "London",
        region: "California",
        regionId: 12,
        postcode: "94016",
        telephone: "555-0100",
        ...overrides,
    };
}

describe("emptyAddress", () => {
    it("seeds the given country and two blank street lines", () => {
        const address = emptyAddress("PT");
        expect(address.countryId).toBe("PT");
        expect(address.street).toEqual(["", ""]);
        expect(address.regionId).toBeNull();
    });
});

describe("missingFields", () => {
    it("returns nothing for a complete address", () => {
        expect(missingFields(filled(), true)).toEqual([]);
    });

    it("flags each blank required field in form order", () => {
        const address = emptyAddress("US");
        expect(missingFields(address, false)).toEqual([
            "firstname",
            "lastname",
            "street0",
            "city",
            "postcode",
            "telephone",
        ]);
    });

    it("requires a region only when the country mandates a state", () => {
        const address = filled({ region: "", regionId: null });
        expect(missingFields(address, false)).toEqual([]);
        expect(missingFields(address, true)).toEqual(["region"]);
    });

    it("accepts a region id alone as satisfying the region requirement", () => {
        const address = filled({ region: "", regionId: 5 });
        expect(missingFields(address, true)).toEqual([]);
    });

    it("treats a whitespace-only field as missing", () => {
        expect(missingFields(filled({ city: "   " }), true)).toEqual(["city"]);
    });
});

describe("toRestAddress", () => {
    it("maps to Magento's snake_case envelope and drops empty street lines", () => {
        const rest = toRestAddress(filled({ street: ["1 Analytical Way", ""] }));
        expect(rest).toMatchObject({
            country_id: "US",
            region: "California",
            region_id: 12,
            street: ["1 Analytical Way"],
            city: "London",
            postcode: "94016",
            telephone: "555-0100",
            firstname: "Ada",
            lastname: "Lovelace",
        });
    });

    it("keeps a single blank line when no street is given", () => {
        expect(toRestAddress(emptyAddress("US")).street).toEqual([""]);
    });

    it("omits company, region_code and email unless provided", () => {
        const rest = toRestAddress(filled());
        expect(rest).not.toHaveProperty("company");
        expect(rest).not.toHaveProperty("region_code");
        expect(rest).not.toHaveProperty("email");
    });

    it("adds the guest email, region code and save flag from extra", () => {
        const rest = toRestAddress(filled({ company: "ACME" }), {
            email: "ada@shop.test",
            regionCode: "CA",
            saveInAddressBook: false,
        });
        expect(rest.company).toBe("ACME");
        expect(rest.email).toBe("ada@shop.test");
        expect(rest.region_code).toBe("CA");
        expect(rest.save_in_address_book).toBe(0);
    });
});

describe("addressFieldLabel", () => {
    it("names every field missingFields can report", () => {
        const all = missingFields(emptyAddress(), true);
        expect(all.length).toBeGreaterThan(0);
        for (const field of all) {
            expect(addressFieldLabel(field)).not.toBe(field);
        }
    });

    it("maps the two field names that differ from their label key", () => {
        expect(addressFieldLabel("street0")).toBe("Street address");
        expect(addressFieldLabel("countryId")).toBe("Country");
    });

    it("prefers the translated label the theme passes down", () => {
        expect(addressFieldLabel("street0", { street: "Dirección" })).toBe("Dirección");
        expect(addressFieldLabel("firstname", { firstname: "Nombre" })).toBe("Nombre");
    });

    it("falls back to the field name for something it does not know", () => {
        expect(addressFieldLabel("loyaltyId")).toBe("loyaltyId");
    });
});

function rulesWith(fields: AddressRules["fields"] = {}, extra: Partial<AddressRules> = {}): AddressRules {
    const base = defaultAddressRules();
    return { ...base, ...extra, fields: { ...base.fields, ...fields } };
}

describe("defaultAddressRules", () => {
    it("matches the platform defaults: telephone required, company optional, the rest hidden", () => {
        const { fields, streetLines, optionalZipCountries } = defaultAddressRules();
        expect(fields.telephone).toEqual({ visible: true, required: true, options: [] });
        expect(fields.company).toEqual({ visible: true, required: false, options: [] });
        expect(fields.prefix.visible).toBe(false);
        expect(fields.fax.visible).toBe(false);
        expect(streetLines).toBe(2);
        expect(optionalZipCountries).toEqual([]);
    });
});

describe("postcodeRequired", () => {
    it("is required unless the country is declared optional", () => {
        const rules = rulesWith({}, { optionalZipCountries: ["IE"] });
        expect(postcodeRequired("US", rules)).toBe(true);
        expect(postcodeRequired("IE", rules)).toBe(false);
    });
});

describe("missingFields with store rules", () => {
    it("does not ask for a postcode where the country declares it optional", () => {
        const rules = rulesWith({}, { optionalZipCountries: ["IE"] });
        const address = filled({ countryId: "IE", postcode: "", region: "", regionId: null });
        expect(missingFields(address, false, rules)).toEqual([]);
        expect(missingFields({ ...address, countryId: "US" }, false, rules)).toEqual(["postcode"]);
    });

    it("does not ask for a telephone the store made optional", () => {
        const rules = rulesWith({ telephone: { visible: true, required: false, options: [] } });
        expect(missingFields(filled({ telephone: "" }), false, rules)).toEqual([]);
    });

    it("does not ask for a telephone the store hides", () => {
        const rules = rulesWith({ telephone: { visible: false, required: false, options: [] } });
        expect(missingFields(filled({ telephone: "" }), false, rules)).toEqual([]);
    });

    it("asks for every visible field the store makes required", () => {
        const rules = rulesWith({
            prefix: { visible: true, required: true, options: ["Mr.", "Mrs."] },
            company: { visible: true, required: true, options: [] },
            vat_id: { visible: true, required: true, options: [] },
        });
        expect(missingFields(filled(), false, rules)).toEqual(["prefix", "company", "vatId"]);
    });

    it("ignores a required flag on a hidden field", () => {
        const rules = rulesWith({ company: { visible: false, required: true, options: [] } });
        expect(missingFields(filled(), false, rules)).toEqual([]);
    });
});

describe("emptyAddress with the configured street lines", () => {
    it("seeds as many blank lines as the store configures", () => {
        expect(emptyAddress("US", 3).street).toEqual(["", "", ""]);
    });

    it("seeds the optional name fields blank", () => {
        const address = emptyAddress("US");
        expect([address.prefix, address.middlename, address.suffix, address.fax, address.vatId]).toEqual(["", "", "", "", ""]);
    });
});

describe("toRestAddress with the optional fields", () => {
    it("carries each filled optional field under its REST name", () => {
        const rest = toRestAddress(
            filled({ prefix: "Dr.", middlename: "Byron", suffix: "Jr.", fax: "555-0199", vatId: "GB123" }),
        );
        expect(rest).toMatchObject({ prefix: "Dr.", middlename: "Byron", suffix: "Jr.", fax: "555-0199", vat_id: "GB123" });
    });

    it("omits the optional fields left blank", () => {
        const rest = toRestAddress(filled());
        for (const key of ["prefix", "middlename", "suffix", "fax", "vat_id"]) {
            expect(rest).not.toHaveProperty(key);
        }
    });
});
