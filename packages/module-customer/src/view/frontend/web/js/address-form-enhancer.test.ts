import { describe, it, expect, beforeEach } from "vitest";
import { computeRegionState, enhanceAddressForm } from "./address-form-enhancer.ts";

const REGION_JSON = {
    config: { show_all_regions: true, regions_required: ["US", "ES"] },
    US: [
        { id: 1, name: "Alabama" },
        { id: 2, name: "Alaska" },
    ],
};

describe("computeRegionState", () => {
    it("uses a populated select for a country with regions", () => {
        const state = computeRegionState(REGION_JSON, "US");
        expect(state.mode).toBe("select");
        expect(state.required).toBe(true);
        expect(state.regions).toHaveLength(2);
    });

    it("falls back to a free-text input for a country without regions", () => {
        const state = computeRegionState(REGION_JSON, "GB");
        expect(state.mode).toBe("text");
        expect(state.required).toBe(false);
        expect(state.regions).toEqual([]);
    });

    it("reads requiredness from the directory config even without region data", () => {
        const state = computeRegionState({ config: { regions_required: ["XX"] } }, "XX");
        expect(state.mode).toBe("text");
        expect(state.required).toBe(true);
    });
});

describe("enhanceAddressForm region swapping", () => {
    function buildForm(): HTMLFormElement {
        document.body.innerHTML = `
            <form data-address-form data-err-required="Required">
                <script type="application/json" data-region-json>${JSON.stringify(REGION_JSON)}</script>
                <input name="firstname" value="Ada" />
                <input name="lastname" value="Lovelace" />
                <input name="telephone" value="555" />
                <input name="street[0]" value="1 Way" />
                <input name="city" value="Town" />
                <input name="postcode" value="0000" />
                <select name="country_id" data-country>
                    <option value="US" selected>United States</option>
                    <option value="GB">United Kingdom</option>
                </select>
                <select name="region_id" data-region-select>
                    <option value="">Pick one</option>
                    <option value="1">Alabama</option>
                </select>
                <input name="region" data-region-input class="hidden" disabled />
                <button type="submit">Save</button>
            </form>`;
        return document.querySelector("form") as HTMLFormElement;
    }

    beforeEach(() => {
        document.body.innerHTML = "";
    });

    it("swaps to the free-text input when the country has no regions", () => {
        const form = buildForm();
        enhanceAddressForm(form);

        const country = form.querySelector("[data-country]") as HTMLSelectElement;
        const select = form.querySelector("[data-region-select]") as HTMLSelectElement;
        const input = form.querySelector("[data-region-input]") as HTMLInputElement;

        country.value = "GB";
        country.dispatchEvent(new Event("change"));

        expect(select.disabled).toBe(true);
        expect(select.classList.contains("hidden")).toBe(true);
        expect(input.disabled).toBe(false);
        expect(input.classList.contains("hidden")).toBe(false);
    });

    it("repopulates the select when switching back to a country with regions", () => {
        const form = buildForm();
        enhanceAddressForm(form);

        const country = form.querySelector("[data-country]") as HTMLSelectElement;
        const select = form.querySelector("[data-region-select]") as HTMLSelectElement;

        country.value = "GB";
        country.dispatchEvent(new Event("change"));
        country.value = "US";
        country.dispatchEvent(new Event("change"));

        expect(select.disabled).toBe(false);
        expect(select.classList.contains("hidden")).toBe(false);
        // Placeholder + the two US regions.
        expect(select.options).toHaveLength(3);
        expect(select.options[1].text).toBe("Alabama");
    });

    it("requires the region select while the country needs one", () => {
        const form = buildForm();
        enhanceAddressForm(form);

        // US selected, region empty → blocked.
        let submit = new Event("submit", { cancelable: true });
        form.dispatchEvent(submit);
        expect(submit.defaultPrevented).toBe(true);

        // Pick a region → passes.
        (form.querySelector("[data-region-select]") as HTMLSelectElement).value = "1";
        submit = new Event("submit", { cancelable: true });
        form.dispatchEvent(submit);
        expect(submit.defaultPrevented).toBe(false);
    });

    it("drops the region requirement for a country without regions", () => {
        const form = buildForm();
        enhanceAddressForm(form);

        const country = form.querySelector("[data-country]") as HTMLSelectElement;
        country.value = "GB";
        country.dispatchEvent(new Event("change"));

        // Region untouched but no longer required → submit proceeds.
        const submit = new Event("submit", { cancelable: true });
        form.dispatchEvent(submit);
        expect(submit.defaultPrevented).toBe(false);
    });
});
