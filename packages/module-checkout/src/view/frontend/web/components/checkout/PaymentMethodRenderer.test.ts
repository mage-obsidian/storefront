import { describe, it, expect } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import PaymentMethodRenderer from "./PaymentMethodRenderer.vue";

const settle = async (): Promise<void> => {
    for (let pass = 0; pass < 10; pass += 1) {
        await new Promise((resolve) => setTimeout(resolve, 10));
        await flushPromises();
    }
};
const PROBE = "../../../../../Test/Js/stubs/ProbeRenderer.vue";
const BROKEN = "../../../../../Test/Js/stubs/BrokenRenderer.vue";

const render = (component: string) =>
    mount(PaymentMethodRenderer, {
        props: {
            code: "verification_probe",
            title: "Verification probe card",
            component,
            selected: true,
            config: { payableTo: "The Store" },
        },
    });

describe("PaymentMethodRenderer", () => {
    it("mounts the component the descriptor named", async () => {
        const wrapper = render(PROBE);
        await settle();

        expect(wrapper.find("[data-probe-renderer=verification_probe]").exists()).toBe(true);
        expect(wrapper.text()).toContain("Verification probe card");
    });

    it("hands the renderer its method and its native config", async () => {
        const wrapper = render(PROBE);
        await settle();

        expect(wrapper.find("[data-probe-renderer]").attributes("data-selected")).toBe("");
    });

    it("relays the renderer's own selection", async () => {
        const wrapper = render(PROBE);
        await settle();
        await wrapper.find("[data-probe-renderer]").trigger("click");

        expect(wrapper.emitted("select")).toHaveLength(1);
    });

    it("reports the method as failed when its component cannot mount", async () => {
        const wrapper = render(BROKEN);
        await settle();

        expect(wrapper.emitted("failed")?.[0]).toEqual(["verification_probe"]);
        expect(wrapper.html()).not.toContain("data-probe-renderer");
    });

    it("reports the method as failed when its component cannot be fetched", async () => {
        const wrapper = render("/no/such/renderer.js");
        await settle();

        expect(wrapper.emitted("failed")?.[0]).toEqual(["verification_probe"]);
    });
});
