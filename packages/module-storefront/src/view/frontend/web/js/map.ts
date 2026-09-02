import { i18n } from "mage-obsidian/runtime/i18nCore.ts";

export interface MapLocation {
    latitude: number;
    longitude: number;
    name: string;
    address: string;
    phone: string;
}

export interface MapLabels {
    show: string;
    directions: string;
    unavailable: string;
}

const DEFAULT_LABELS: MapLabels = {
    show: "Show the interactive map",
    directions: "Open in Google Maps",
    unavailable: "The interactive map is unavailable.",
};

const bound = new WeakSet<Element>();

const text = (value: unknown): string => (typeof value === "string" ? value.trim() : "");

export function locationsOf(root: Element): MapLocation[] {
    let parsed: unknown;
    try {
        parsed = JSON.parse(root.getAttribute("data-locations") ?? "[]");
    } catch {
        return [];
    }
    if (!Array.isArray(parsed)) {
        return [];
    }

    return parsed
        .map((entry) => {
            const record = (entry ?? {}) as Record<string, unknown>;
            const position = (record.position ?? {}) as Record<string, unknown>;
            const latitude = Number.parseFloat(String(position.latitude ?? ""));
            const longitude = Number.parseFloat(String(position.longitude ?? ""));

            return {
                latitude,
                longitude,
                name: text(record.location_name),
                address: text(record.address),
                phone: text(record.phone),
            };
        })
        .filter((location) => Number.isFinite(location.latitude) && Number.isFinite(location.longitude));
}

export function directionsUrl(location: MapLocation): string {
    return `https://www.google.com/maps/search/?api=1&query=${location.latitude},${location.longitude}`;
}

export function embedUrl(location: MapLocation, apiKey: string): string | null {
    if (apiKey.trim() === "") {
        return null;
    }

    const query = encodeURIComponent(`${location.latitude},${location.longitude}`);

    return `https://www.google.com/maps/embed/v1/place?key=${encodeURIComponent(apiKey)}&q=${query}`;
}

function summary(location: MapLocation, doc: Document, labels: MapLabels): HTMLElement {
    const card = doc.createElement("div");
    card.setAttribute("data-map-summary", "");

    for (const [marker, value] of [
        ["name", location.name],
        ["address", location.address],
        ["phone", location.phone],
    ] as const) {
        if (value === "") {
            continue;
        }
        const line = doc.createElement("p");
        line.setAttribute(`data-map-${marker}`, "");
        line.textContent = value;
        card.appendChild(line);
    }

    const directions = doc.createElement("a");
    directions.href = directionsUrl(location);
    directions.rel = "noopener noreferrer";
    directions.target = "_blank";
    directions.textContent = labels.directions;
    directions.setAttribute("data-map-directions", "");
    card.appendChild(directions);

    return card;
}

export function enhanceMap(
    root: HTMLElement,
    view: Window = window,
    options: { apiKey?: string; labels?: Partial<MapLabels> } = {},
): boolean {
    if (bound.has(root)) {
        return false;
    }

    const locations = locationsOf(root);
    if (locations.length === 0) {
        return false;
    }

    bound.add(root);
    const doc = view.document;
    const labels = { ...DEFAULT_LABELS, ...options.labels };
    const apiKey = options.apiKey ?? root.getAttribute("data-map-api-key") ?? "";
    const location = locations[0];

    root.setAttribute("data-map-state", "summary");
    root.appendChild(summary(location, doc, labels));

    const embed = embedUrl(location, apiKey);
    if (embed === null) {
        return true;
    }

    const request = doc.createElement("button");
    request.type = "button";
    request.textContent = labels.show;
    request.setAttribute("data-map-request", "");
    request.addEventListener("click", () => {
        const frame = doc.createElement("iframe");
        frame.src = embed;
        frame.title = location.name !== "" ? location.name : labels.show;
        frame.loading = "lazy";
        frame.referrerPolicy = "no-referrer-when-downgrade";
        frame.setAttribute("data-map-frame", "");
        request.remove();
        root.setAttribute("data-map-state", "interactive");
        root.appendChild(frame);
    });
    root.appendChild(request);

    return true;
}

export function enhanceMaps(
    root: ParentNode = document,
    view: Window = window,
    options: { apiKey?: string; labels?: Partial<MapLabels> } = {},
): void {
    root.querySelectorAll<HTMLElement>('[data-content-type="map"]').forEach((element) =>
        enhanceMap(element, view, options),
    );
}

enhanceMaps(document, window, {
    labels: {
        show: i18n.$t("Show the interactive map"),
        directions: i18n.$t("Open in Google Maps"),
        unavailable: i18n.$t("The interactive map is unavailable."),
    },
});
