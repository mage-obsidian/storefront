import { describe, it, expect, afterEach } from "vitest";
import { mount } from "@vue/test-utils";
import Field from "./Field.vue";

afterEach(() => {
    document.body.innerHTML = "";
});

const mountField = (props: Record<string, unknown> = {}) =>
    mount(Field, { props: { label: "Email", name: "email", ...props }, attachTo: document.body });

describe("Field", () => {
    it("ties the label, the control and the error node together", () => {
        const wrapper = mountField();

        const input = wrapper.get("input");
        const id = input.attributes("id") as string;
        expect(id).toBeTruthy();
        expect(wrapper.get("label").attributes("for")).toBe(id);
        expect(wrapper.get(".field__error").attributes("id")).toBe(`${id}-error`);
        expect(input.attributes("aria-describedby")).toBe(`${id}-error`);

        wrapper.unmount();
    });

    it("gives every field on a form its own ids", () => {
        const wrapper = mount(
            {
                components: { Field },
                template: `
                    <form>
                        <Field label="Email" name="email" />
                        <Field label="Password" name="password" type="password" />
                    </form>`,
            },
            { attachTo: document.body },
        );

        const ids = wrapper.findAll("input").map((input) => input.attributes("id"));
        expect(new Set(ids).size).toBe(2);
        expect(wrapper.findAll("label").map((label) => label.attributes("for"))).toEqual(ids);

        wrapper.unmount();
    });

    it("marks a required field for both sighted users and assistive tech", () => {
        const wrapper = mountField({ required: true });

        expect(wrapper.get(".field__required").text()).toBe("*");
        expect(wrapper.get(".field__required").attributes("aria-hidden")).toBe("true");
        expect(wrapper.get("input").attributes("required")).toBeDefined();
        expect(wrapper.get("input").attributes("aria-required")).toBe("true");

        wrapper.unmount();
    });

    it("leaves an optional field unmarked", () => {
        const wrapper = mountField();

        expect(wrapper.find(".field__required").exists()).toBe(false);
        expect(wrapper.get("input").attributes("aria-required")).toBeUndefined();

        wrapper.unmount();
    });

    // The node is always in the DOM so `aria-describedby` never dangles and the
    // message cannot arrive before its styles; `:empty` hides it.
    it("always renders the error node, empty when there is nothing to say", () => {
        const wrapper = mountField();

        const error = wrapper.get(".field__error");
        expect(error.text()).toBe("");
        expect(error.attributes("role")).toBe("alert");
        expect(wrapper.get("input").attributes("aria-invalid")).toBeUndefined();

        wrapper.unmount();
    });

    it("flags the control when an error is passed in", () => {
        const wrapper = mountField({ error: "This is a required field." });

        expect(wrapper.get(".field__error").text()).toBe("This is a required field.");
        expect(wrapper.get("input").attributes("aria-invalid")).toBe("true");

        wrapper.unmount();
    });

    it("describes the control by its hint as well", () => {
        const wrapper = mountField({ hint: "We never share it." });

        const id = wrapper.get("input").attributes("id");
        expect(wrapper.get(".field__hint").attributes("id")).toBe(`${id}-hint`);
        expect(wrapper.get("input").attributes("aria-describedby")).toBe(`${id}-hint ${id}-error`);

        wrapper.unmount();
    });

    it("round-trips the value through v-model", async () => {
        const wrapper = mountField({ modelValue: "ada@shop.test" });

        expect((wrapper.get("input").element as HTMLInputElement).value).toBe("ada@shop.test");

        await wrapper.get("input").setValue("grace@shop.test");
        expect(wrapper.props("modelValue")).toBe("ada@shop.test");
        expect(wrapper.emitted("update:modelValue")?.at(-1)).toEqual(["grace@shop.test"]);

        wrapper.unmount();
    });

    it("renders a textarea and a select from the same API", () => {
        const area = mountField({ type: "textarea" });
        expect(area.get("textarea").classes()).toContain("field__control");
        area.unmount();

        const select = mountField({
            type: "select",
            options: [
                { value: "1", label: "One" },
                { value: "2", label: "Two" },
            ],
        });
        expect(select.findAll("option")).toHaveLength(2);
        expect(select.get("select").classes()).toContain("field__control");
        select.unmount();
    });

    it("honours an explicit id so a server-rendered form can hydrate onto it", () => {
        const wrapper = mountField({ id: "login-email" });

        expect(wrapper.get("input").attributes("id")).toBe("login-email");
        expect(wrapper.get("label").attributes("for")).toBe("login-email");
        expect(wrapper.get(".field__error").attributes("id")).toBe("login-email-error");

        wrapper.unmount();
    });

    it("forwards blur from the control, which a fallthrough listener would miss", async () => {
        const wrapper = mountField();

        await wrapper.get("input").trigger("blur");

        expect(wrapper.emitted("blur")).toHaveLength(1);

        wrapper.unmount();
    });
});
