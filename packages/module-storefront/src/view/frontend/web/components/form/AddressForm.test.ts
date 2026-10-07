// This file is part of the MageObsidian - Storefront project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import AddressForm from "./AddressForm.vue";
import { defaultAddressRules, emptyAddress, type AddressData, type AddressRules } from "MageObsidian_Storefront::js/address";

const DIRECTORY = {
    countries: [
        { value: "US", label: "United States" },
        { value: "PT", label: "Portugal" },
    ],
    regions: {
        US: [
            { id: 1, code: "AL", name: "Alabama" },
            { id: 12, code: "CA", name: "California" },
        ],
    },
    statesRequired: ["US"],
    displayAllRegions: false,
};

function mountForm(address: AddressData) {
    return mount(AddressForm, {
        props: { ...DIRECTORY, modelValue: address, "onUpdate:modelValue": (v: AddressData) => Object.assign(address, v) },
    });
}

describe("AddressForm", () => {
    it("shows a region select when the country has predefined regions", () => {
        const wrapper = mountForm(emptyAddress("US"));
        const select = wrapper.findAll("select");
        // country select + region select
        expect(select).toHaveLength(2);
        expect(wrapper.html()).toContain("California");
    });

    it("falls back to a free-text region for a country without regions", () => {
        const wrapper = mountForm(emptyAddress("PT"));
        expect(wrapper.findAll("select")).toHaveLength(1);
    });

    it("syncs the region id and name when an option is picked", async () => {
        const address = emptyAddress("US");
        const wrapper = mountForm(address);
        const regionSelect = wrapper.findAll("select")[1];

        await regionSelect.setValue("12");

        expect(address.regionId).toBe(12);
        expect(address.region).toBe("California");
    });

    it("resets the region when the country changes", async () => {
        const address = { ...emptyAddress("US"), regionId: 12, region: "California" };
        const wrapper = mountForm(address);

        await wrapper.findAll("select")[0].setValue("PT");

        expect(address.regionId).toBeNull();
        expect(address.region).toBe("");
    });

    it("validate() marks blanks, blocks, and clears once filled", async () => {
        const address = emptyAddress("US");
        const wrapper = mountForm(address);

        expect(wrapper.vm.validate()).toBe(false);
        await wrapper.vm.$nextTick();
        expect(wrapper.findAll('[role="alert"]').length).toBeGreaterThan(0);
        expect(wrapper.html()).toContain("aria-invalid");

        Object.assign(address, {
            firstname: "Ada",
            lastname: "Lovelace",
            street: ["1 Analytical Way", ""],
            city: "London",
            postcode: "94016",
            telephone: "555-0100",
            regionId: 12,
            region: "California",
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.validate()).toBe(true);
    });

    it("marks the fields a caller reports as invalid without any validate() call", async () => {
        const address = emptyAddress("US");
        const wrapper = mount(AddressForm, {
            props: {
                ...DIRECTORY,
                modelValue: address,
                "onUpdate:modelValue": (v: AddressData) => Object.assign(address, v),
                invalidFields: ["firstname"],
            },
        });

        const firstname = wrapper.find("#" + wrapper.findAll("input")[0].attributes("id"));
        expect(firstname.attributes("aria-invalid")).toBe("true");
        expect(wrapper.findAll("input")[1].attributes("aria-invalid")).toBeUndefined();

        await wrapper.setProps({ invalidFields: [] });

        expect(wrapper.findAll("input")[0].attributes("aria-invalid")).toBeUndefined();
    });

    it("focusField() lands on the street control, whose name the helper reports as street0", () => {
        const address = emptyAddress("US");
        const wrapper = mountForm(address);
        document.body.appendChild(wrapper.element as HTMLElement);

        wrapper.vm.focusField("street0");

        const street = wrapper.find('input[autocomplete="address-line1"]').element;
        expect(document.activeElement).toBe(street);
    });

    describe("store rules", () => {
        const rulesWith = (fields: AddressRules["fields"] = {}, extra: Partial<AddressRules> = {}): AddressRules => {
            const base = defaultAddressRules();
            return { ...base, ...extra, fields: { ...base.fields, ...fields } };
        };

        const mountWith = (address: AddressData, rules: AddressRules) =>
            mount(AddressForm, {
                props: {
                    ...DIRECTORY,
                    modelValue: address,
                    "onUpdate:modelValue": (v: AddressData) => Object.assign(address, v),
                    rules,
                },
            });

        it("omits a company the store hides", () => {
            const wrapper = mountWith(
                emptyAddress("US"),
                rulesWith({ company: { visible: false, required: false, options: [] } }),
            );
            expect(wrapper.find('input[autocomplete="organization"]').exists()).toBe(false);
        });

        it("offers the prefix as a list when the store configures options", () => {
            const wrapper = mountWith(
                emptyAddress("US"),
                rulesWith({ prefix: { visible: true, required: false, options: ["Mr.", "Mrs."] } }),
            );
            const prefix = wrapper.find('select[autocomplete="honorific-prefix"]');
            expect(prefix.exists()).toBe(true);
            expect(prefix.findAll("option").map((option) => option.text())).toEqual(["", "Mr.", "Mrs."]);
        });

        it("offers the prefix as free text when the store configures no options", () => {
            const wrapper = mountWith(
                emptyAddress("US"),
                rulesWith({ prefix: { visible: true, required: false, options: [] } }),
            );
            expect(wrapper.find('input[autocomplete="honorific-prefix"]').exists()).toBe(true);
        });

        it("shows the middle name, suffix, fax and VAT number the store turns on", () => {
            const wrapper = mountWith(
                emptyAddress("US"),
                rulesWith({
                    middlename: { visible: true, required: false, options: [] },
                    suffix: { visible: true, required: false, options: [] },
                    fax: { visible: true, required: false, options: [] },
                    vat_id: { visible: true, required: false, options: [] },
                }),
            );
            for (const autocomplete of ["additional-name", "honorific-suffix", "fax tel"]) {
                expect(wrapper.find(`input[autocomplete="${autocomplete}"]`).exists()).toBe(true);
            }
            expect(wrapper.find("[data-field-vat-id]").exists()).toBe(true);
        });

        it("does not require a telephone the store makes optional", async () => {
            const address = {
                ...emptyAddress("US"),
                firstname: "Ada",
                lastname: "Lovelace",
                street: ["1 Analytical Way", ""],
                city: "London",
                postcode: "94016",
                regionId: 12,
                region: "California",
            };
            const wrapper = mountWith(address, rulesWith({ telephone: { visible: true, required: false, options: [] } }));

            expect(wrapper.find('input[autocomplete="tel"]').attributes("required")).toBeUndefined();
            expect(wrapper.vm.validate()).toBe(true);
        });

        it("omits a telephone the store hides", () => {
            const wrapper = mountWith(
                emptyAddress("US"),
                rulesWith({ telephone: { visible: false, required: false, options: [] } }),
            );
            expect(wrapper.find('input[autocomplete="tel"]').exists()).toBe(false);
        });

        it("does not require a postcode in a country the store declares optional", () => {
            const address = {
                ...emptyAddress("PT"),
                firstname: "Ada",
                lastname: "Lovelace",
                street: ["1 Analytical Way", ""],
                city: "Lisbon",
                telephone: "555-0100",
            };
            const wrapper = mountWith(address, rulesWith({}, { optionalZipCountries: ["PT"] }));

            expect(wrapper.find('input[autocomplete="postal-code"]').attributes("required")).toBeUndefined();
            expect(wrapper.vm.validate()).toBe(true);
        });

        it("renders as many street lines as the store configures", () => {
            const wrapper = mountWith(emptyAddress("US", 3), rulesWith({}, { streetLines: 3 }));
            expect(wrapper.findAll('input[autocomplete^="address-line"]')).toHaveLength(3);
        });
    });
});
