import { beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import ObsidianMap from "./ObsidianMap.vue";

const enhanceMap = vi.fn();
vi.mock("MageObsidian_Storefront::js/map", () => ({ enhanceMap: (...args: unknown[]) => enhanceMap(...args) }));

const props = {
    latitude: "-34.6037",
    longitude: "-58.3816",
    locationName: "Obsidian Buenos Aires",
    address: "Av. Corrientes 1234",
    phone: "+54 11 5555 5555",
};

beforeEach(() => {
    enhanceMap.mockClear();
    document.body.innerHTML = "";
});

describe("ObsidianMap", () => {
    it("hands the author's pin to the markup the behaviour module reads", () => {
        const wrapper = mount(ObsidianMap, { props });

        expect(JSON.parse(wrapper.attributes("data-locations")!)).toEqual([
            {
                position: { latitude: "-34.6037", longitude: "-58.3816" },
                location_name: "Obsidian Buenos Aires",
                address: "Av. Corrientes 1234",
                phone: "+54 11 5555 5555",
            },
        ]);
    });

    it("draws nothing itself and lets the behaviour module do the work", () => {
        const wrapper = mount(ObsidianMap, { props, attachTo: document.body });

        expect(wrapper.element.children).toHaveLength(0);
        expect(enhanceMap).toHaveBeenCalledTimes(1);
        expect(enhanceMap.mock.calls[0][0]).toBe(wrapper.element);
    });

    it("takes the provider key from the marker the server wrote it on", () => {
        document.body.innerHTML = '<div data-map-api-key="SECRET" id="marker"></div>';
        mount(ObsidianMap, { props, attachTo: document.getElementById("marker")! });

        expect(enhanceMap.mock.calls[0][2]).toEqual({ apiKey: "SECRET" });
    });

    it("asks for no key at all when the server wrote none", () => {
        mount(ObsidianMap, { props, attachTo: document.body });

        expect(enhanceMap.mock.calls[0][2]).toEqual({ apiKey: "" });
    });

    it("still carries a pin the author only half filled", () => {
        const wrapper = mount(ObsidianMap, { props: { latitude: "1", longitude: "2" } });

        expect(JSON.parse(wrapper.attributes("data-locations")!)[0]).toMatchObject({
            position: { latitude: "1", longitude: "2" },
            location_name: "",
        });
    });
});
