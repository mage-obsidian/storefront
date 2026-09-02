import { beforeEach, describe, expect, it } from "vitest";
import { directionsUrl, embedUrl, enhanceMap, enhanceMaps, locationsOf } from "./map.ts";

const LOCATION = {
    position: { latitude: "-34.6037", longitude: "-58.3816" },
    location_name: "Obsidian Buenos Aires",
    address: "Av. Corrientes 1234",
    phone: "+54 11 5555 5555",
};

const build = (locations: unknown = [LOCATION], extra = ""): HTMLElement => {
    const attribute = JSON.stringify(locations).replace(/"/g, "&quot;");
    document.body.innerHTML = `<div data-content-type="map" data-locations="${attribute}" ${extra}></div>`;
    return document.querySelector<HTMLElement>('[data-content-type="map"]')!;
};

beforeEach(() => {
    document.body.innerHTML = "";
});

describe("locationsOf", () => {
    it("reads what the author pinned", () => {
        expect(locationsOf(build())).toEqual([
            {
                latitude: -34.6037,
                longitude: -58.3816,
                name: "Obsidian Buenos Aires",
                address: "Av. Corrientes 1234",
                phone: "+54 11 5555 5555",
            },
        ]);
    });

    it("reads nothing out of an empty, malformed or missing attribute", () => {
        expect(locationsOf(build([]))).toEqual([]);
        document.body.innerHTML = '<div data-content-type="map" data-locations="{"></div>';
        expect(locationsOf(document.querySelector("[data-content-type]")!)).toEqual([]);
        document.body.innerHTML = '<div data-content-type="map"></div>';
        expect(locationsOf(document.querySelector("[data-content-type]")!)).toEqual([]);
    });

    it("drops a pin with no usable coordinates", () => {
        expect(locationsOf(build([{ position: { latitude: "x", longitude: "y" } }]))).toEqual([]);
    });

    it("tolerates a pin the author left half filled", () => {
        expect(locationsOf(build([{ position: { latitude: "1", longitude: "2" } }]))).toEqual([
            { latitude: 1, longitude: 2, name: "", address: "", phone: "" },
        ]);
    });
});

describe("embedUrl", () => {
    const location = { latitude: 1, longitude: 2, name: "", address: "", phone: "" };

    it("centres the provider's map on the author's pin", () => {
        expect(embedUrl(location, "KEY")).toBe(
            "https://www.google.com/maps/embed/v1/place?key=KEY&q=1%2C2",
        );
    });

    it("has no url to offer when no provider is configured", () => {
        expect(embedUrl(location, "")).toBeNull();
        expect(embedUrl(location, "   ")).toBeNull();
    });

    it("points a link at the pin without asking the provider for anything", () => {
        expect(directionsUrl(location)).toBe("https://www.google.com/maps/search/?api=1&query=1,2");
    });
});

describe("enhanceMap", () => {
    it("says where the place is without loading anything from a third party", () => {
        const root = build();

        enhanceMap(root, window, { apiKey: "KEY" });

        expect(root.querySelector("[data-map-name]")?.textContent).toBe("Obsidian Buenos Aires");
        expect(root.querySelector("[data-map-address]")?.textContent).toBe("Av. Corrientes 1234");
        expect(root.querySelector("[data-map-phone]")?.textContent).toBe("+54 11 5555 5555");
        expect(root.querySelector("iframe")).toBeNull();
        expect(root.querySelector("script")).toBeNull();
        expect(root.getAttribute("data-map-state")).toBe("summary");
    });

    it("loads the provider's map only once the visitor asks for it", () => {
        const root = build();
        enhanceMap(root, window, { apiKey: "KEY" });

        expect(root.querySelector("iframe")).toBeNull();
        root.querySelector<HTMLButtonElement>("[data-map-request]")!.click();

        const frame = root.querySelector("iframe")!;
        expect(frame.getAttribute("src")).toContain("maps/embed/v1/place");
        expect(frame.getAttribute("src")).toContain("q=-34.6037%2C-58.3816");
        expect(root.getAttribute("data-map-state")).toBe("interactive");
    });

    it("still names the place when no provider is configured", () => {
        const root = build();

        enhanceMap(root, window, { apiKey: "" });

        expect(root.querySelector("[data-map-name]")?.textContent).toBe("Obsidian Buenos Aires");
        expect(root.querySelector("[data-map-request]")).toBeNull();
        expect(root.querySelector("[data-map-directions]")?.getAttribute("href")).toBe(
            "https://www.google.com/maps/search/?api=1&query=-34.6037,-58.3816",
        );
    });

    it("takes the key off the markup when the caller passes none", () => {
        const root = build([LOCATION], 'data-map-api-key="FROM-MARKUP"');

        enhanceMap(root, window);
        root.querySelector<HTMLButtonElement>("[data-map-request]")!.click();

        expect(root.querySelector("iframe")!.getAttribute("src")).toContain("key=FROM-MARKUP");
    });

    it("does nothing to a map the author pinned nothing on", () => {
        const root = build([]);

        expect(enhanceMap(root, window, { apiKey: "KEY" })).toBe(false);
        expect(root.getAttribute("data-map-state")).toBeNull();
        expect(root.children).toHaveLength(0);
    });

    it("enhances a given map only once", () => {
        const root = build();

        expect(enhanceMap(root, window, { apiKey: "KEY" })).toBe(true);
        expect(enhanceMap(root, window, { apiKey: "KEY" })).toBe(false);
        expect(root.querySelectorAll("[data-map-summary]")).toHaveLength(1);
    });

    it("takes the words it shows from the caller", () => {
        const root = build();

        enhanceMap(root, window, {
            apiKey: "KEY",
            labels: { show: "Ver el mapa", directions: "Cómo llegar" },
        });

        expect(root.querySelector("[data-map-request]")?.textContent).toBe("Ver el mapa");
        expect(root.querySelector("[data-map-directions]")?.textContent).toBe("Cómo llegar");
    });
});

describe("enhanceMaps", () => {
    it("finds every map on the page", () => {
        const attribute = JSON.stringify([LOCATION]).replace(/"/g, "&quot;");
        document.body.innerHTML =
            `<div data-content-type="map" data-locations="${attribute}"></div>` +
            `<div data-content-type="map" data-locations="[]"></div>` +
            `<div data-content-type="map" data-locations="${attribute}"></div>`;

        enhanceMaps(document, window, { apiKey: "KEY" });

        expect(document.querySelectorAll("[data-map-state]")).toHaveLength(2);
    });
});
