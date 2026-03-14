import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import AddressForm from "./AddressForm.vue";
import { emptyAddress, type AddressData } from "MageObsidian_Storefront::js/address";

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
});
