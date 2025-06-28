import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import GiftMessageForm from "./GiftMessageForm.vue";

const labels = {
    fromLabel: "From",
    toLabel: "To",
    messageLabel: "Message",
    saveLabel: "Save",
    savedLabel: "Saved",
};

function mountForm(initial = { sender: "", recipient: "", message: "" }) {
    return mount(GiftMessageForm, { props: { title: "Order", ...initial, ...labels } });
}

describe("GiftMessageForm", () => {
    it("prefills the fields from props", () => {
        const wrapper = mountForm({ sender: "Jean", recipient: "Alex", message: "Hi" });
        const inputs = wrapper.findAll("input");
        expect(inputs[0].element.value).toBe("Jean");
        expect(inputs[1].element.value).toBe("Alex");
        expect(wrapper.find("textarea").element.value).toBe("Hi");
    });

    it("emits save with the edited values", async () => {
        const wrapper = mountForm();
        await wrapper.findAll("input")[0].setValue("Jean");
        await wrapper.findAll("input")[1].setValue("Mom");
        await wrapper.find("textarea").setValue("For you");
        await wrapper.find("form").trigger("submit");

        expect(wrapper.emitted("save")?.[0]?.[0]).toEqual({ sender: "Jean", recipient: "Mom", message: "For you" });
    });

    it("shows the saved confirmation when the parent resolves ok", async () => {
        const wrapper = mountForm();
        wrapper.vm.resolve(true);
        await wrapper.vm.$nextTick();
        expect(wrapper.text()).toContain("Saved");
    });

    it("shows the error when the parent resolves with a failure", async () => {
        const wrapper = mountForm();
        wrapper.vm.resolve(false, "Could not save");
        await wrapper.vm.$nextTick();
        expect(wrapper.find('[role="alert"]').text()).toBe("Could not save");
    });
});
