// This file is part of the MageObsidian - Checkout project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { describe, it, expect } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import ShippingCarrierRenderer from "./ShippingCarrierRenderer.vue";

const settle = async (): Promise<void> => {
    for (let pass = 0; pass < 10; pass += 1) {
        await new Promise((resolve) => setTimeout(resolve, 10));
        await flushPromises();
    }
};
const CARRIER = "../../../../../Test/Js/stubs/CarrierRenderer.vue";
const BROKEN = "../../../../../Test/Js/stubs/BrokenRenderer.vue";
const PICKUP = { carrier_code: "instore", method_code: "pickup" };

const render = (component: string) =>
    mount(ShippingCarrierRenderer, {
        props: {
            code: "instore",
            component,
            methods: [PICKUP],
            selected: true,
            config: { radius: 200 },
            address: {},
            skus: ["24-MB01", "24-WB03"],
        },
    });

describe("ShippingCarrierRenderer", () => {
    it("mounts the component the descriptor named with the carrier's rates", async () => {
        const wrapper = render(CARRIER);
        await settle();

        const probe = wrapper.find("[data-carrier-renderer-probe=instore]");
        expect(probe.exists()).toBe(true);
        expect(probe.text()).toBe("1");
        expect(probe.attributes("data-selected")).toBe("");
        expect(probe.attributes("data-skus")).toBe("24-MB01,24-WB03");
    });

    it("relays the method and the destination the renderer chose", async () => {
        const wrapper = render(CARRIER);
        await settle();
        await wrapper.find("[data-carrier-renderer-probe]").trigger("click");

        expect(wrapper.emitted("select")?.[0]).toEqual([PICKUP]);
        expect(wrapper.emitted("destination")?.[0]).toEqual([{ carrierCode: "instore" }]);
    });

    it("reports the carrier as failed when its component cannot mount", async () => {
        const wrapper = render(BROKEN);
        await settle();

        expect(wrapper.emitted("failed")?.[0]).toEqual(["instore"]);
    });

    it("reports the carrier as failed when its component cannot be fetched", async () => {
        const wrapper = render("/no/such/renderer.js");
        await settle();

        expect(wrapper.emitted("failed")?.[0]).toEqual(["instore"]);
    });
});
