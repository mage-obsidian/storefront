import { describe, it, expect } from "vitest";
import { mount, type VueWrapper } from "@vue/test-utils";
import OrdersReturnsForm from "./OrdersReturnsForm.vue";
import { STUB_FORM_KEY } from "MageObsidian_Storefront::js/form-key-provider";

function mountForm() {
    return mount(OrdersReturnsForm, {
        props: { action: "https://shop.test/sales/guest/view/" },
    });
}

// v-show toggles display:none on the field's wrapper div, not the input itself.
function fieldShown(wrapper: VueWrapper, name: string): boolean {
    const parent = wrapper.find(`input[name="${name}"]`).element.parentElement;
    return parent?.style.display !== "none";
}

describe("OrdersReturnsForm", () => {
    // The page is cacheable, so the key comes from the cookie at render time, not
    // from a prop the server baked in.
    it("posts to the guest view controller with the live form key", () => {
        const wrapper = mountForm();
        const form = wrapper.find("form");

        expect(form.attributes("action")).toBe("https://shop.test/sales/guest/view/");
        expect(form.attributes("method")).toBe("post");
        expect(wrapper.find('input[name="form_key"]').attributes("value")).toBe(STUB_FORM_KEY);
    });

    it("keeps both identifiers in the DOM (the controller reads both POST keys)", () => {
        const wrapper = mountForm();

        // Both inputs always submit; only the active one is shown and required.
        expect(wrapper.find('input[name="oar_email"]').exists()).toBe(true);
        expect(wrapper.find('input[name="oar_zip"]').exists()).toBe(true);
    });

    it("shows and requires the email identifier by default", () => {
        const wrapper = mountForm();

        expect(fieldShown(wrapper, "oar_email")).toBe(true);
        expect(fieldShown(wrapper, "oar_zip")).toBe(false);
        expect(wrapper.find('input[name="oar_email"]').attributes("required")).toBeDefined();
        expect(wrapper.find('input[name="oar_zip"]').attributes("required")).toBeUndefined();
    });

    it("swaps to the zip identifier when the select changes", async () => {
        const wrapper = mountForm();

        await wrapper.find('select[name="oar_type"]').setValue("zip");

        expect(fieldShown(wrapper, "oar_zip")).toBe(true);
        expect(fieldShown(wrapper, "oar_email")).toBe(false);
        expect(wrapper.find('input[name="oar_zip"]').attributes("required")).toBeDefined();
        expect(wrapper.find('input[name="oar_email"]').attributes("required")).toBeUndefined();
    });
});
