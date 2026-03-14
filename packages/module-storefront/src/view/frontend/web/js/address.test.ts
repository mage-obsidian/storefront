import { describe, it, expect } from "vitest";
import { emptyAddress, missingFields, toRestAddress, type AddressData } from "./address.ts";

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
