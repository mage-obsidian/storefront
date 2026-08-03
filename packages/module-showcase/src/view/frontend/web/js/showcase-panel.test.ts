import { beforeEach, describe, expect, it, vi } from "vitest";
import {
    bindShowcasePanel,
    encodeProfile,
    profileUrl,
    readPanelConfig,
    type PanelConfig,
} from "MageObsidian_Showcase::js/showcase-panel";

const PAGE = "https://demo.test/gear/bags.html";
const CONFIG: PanelConfig = { parameter: "showcase", cookie: "obsidian_showcase", signature: "" };

const MARKUP = `
    <aside data-showcase data-parameter="showcase" data-cookie="obsidian_showcase" data-signature="">
        <button data-showcase-toggle aria-expanded="false"></button>
        <div id="showcase-panel" hidden>
            <select data-showcase-feature="listing_fragments" data-showcase-default="1">
                <option value="1" selected></option>
                <option value="0"></option>
            </select>
            <select data-showcase-feature="checkout_layout" data-showcase-default="stepped">
                <option value="stepped" selected></option>
                <option value="onepage"></option>
            </select>
            <button data-showcase-reset></button>
            <button data-showcase-share></button>
        </div>
    </aside>`;

interface Harness {
    assign: ReturnType<typeof vi.fn>;
    replaceState: ReturnType<typeof vi.fn>;
    writeText: ReturnType<typeof vi.fn>;
    teardown: () => void;
}

const mount = (href = PAGE, config: PanelConfig = CONFIG): Harness => {
    const assign = vi.fn();
    const replaceState = vi.fn();
    const writeText = vi.fn();

    const teardown = bindShowcasePanel(config, {
        location: { href, assign },
        history: { replaceState },
        clipboard: { writeText } as unknown as Clipboard,
    });

    return { assign, replaceState, writeText, teardown };
};

const pick = (key: string, value: string): void => {
    const select = document.querySelector<HTMLSelectElement>(`[data-showcase-feature="${key}"]`)!;
    select.value = value;
    select.dispatchEvent(new Event("change", { bubbles: true }));
};

beforeEach(() => {
    document.body.innerHTML = MARKUP;
    document.cookie = "obsidian_showcase=; Max-Age=0; Path=/";
});

describe("readPanelConfig", () => {
    it("reads the names PHP published", () => {
        expect(readPanelConfig()).toEqual({
            parameter: "showcase",
            cookie: "obsidian_showcase",
            signature: "",
        });
    });

    it("returns nothing when the page did not render the panel", () => {
        document.body.innerHTML = "";

        expect(readPanelConfig()).toBeNull();
    });
});

describe("encodeProfile", () => {
    it("orders keys so one set of choices is one cache entry", () => {
        expect(encodeProfile({ listing_fragments: "0", checkout_layout: "onepage" })).toBe(
            "checkout_layout=onepage~listing_fragments=0",
        );
    });

    it("encodes nothing as nothing", () => {
        expect(encodeProfile({})).toBe("");
    });
});

describe("profileUrl", () => {
    it("carries the profile on the url", () => {
        expect(profileUrl(PAGE, "showcase", "listing_fragments=0")).toBe(
            `${PAGE}?showcase=listing_fragments%3D0`,
        );
    });

    it("drops the parameter for an empty profile", () => {
        expect(profileUrl(`${PAGE}?showcase=a%3D1`, "showcase", "")).toBe(PAGE);
    });

    it("keeps whatever else the url was carrying", () => {
        const url = profileUrl(`${PAGE}?price=30-40`, "showcase", "listing_fragments=0");

        expect(new URL(url).searchParams.get("price")).toBe("30-40");
    });
});

describe("bindShowcasePanel", () => {
    // A plain reload would be answered from the cache keyed on the profile the
    // visitor is leaving; the parameter is a url the cache has never seen.
    it("navigates through the parameter instead of reloading", () => {
        const harness = mount();

        pick("listing_fragments", "0");

        expect(harness.assign).toHaveBeenCalledWith(
            `${PAGE}?showcase=listing_fragments%3D0`,
        );
        harness.teardown();
    });

    it("remembers the choice so later navigations keep it", () => {
        const harness = mount();

        pick("listing_fragments", "0");

        expect(document.cookie).toContain("obsidian_showcase=");
        harness.teardown();
    });

    it("sends only what departs from the store view", () => {
        const harness = mount();

        pick("checkout_layout", "onepage");

        expect(harness.assign.mock.calls[0][0]).toBe(`${PAGE}?showcase=checkout_layout%3Donepage`);
        harness.teardown();
    });

    it("resets back to what the store view says", () => {
        const harness = mount();

        document.querySelector<HTMLElement>("[data-showcase-reset]")!.click();

        expect(harness.assign).toHaveBeenCalledWith(PAGE);
        harness.teardown();
    });

    it("copies a link for the current choices", () => {
        const harness = mount();

        document.querySelector<HTMLElement>("[data-showcase-share]")!.click();

        expect(harness.writeText).toHaveBeenCalledWith(
            PAGE,
        );
        harness.teardown();
    });

    it("opens and closes the drawer", () => {
        const harness = mount();
        const toggle = document.querySelector<HTMLElement>("[data-showcase-toggle]")!;
        const drawer = document.querySelector<HTMLElement>("#showcase-panel")!;

        toggle.click();
        expect(drawer.hidden).toBe(false);
        expect(toggle.getAttribute("aria-expanded")).toBe("true");

        toggle.click();
        expect(drawer.hidden).toBe(true);
        harness.teardown();
    });

    // The server already read it; leaving it on would make every url copied
    // from this page carry a profile the visitor may since have changed.
    it("takes the parameter off the address bar once it has been honoured", () => {
        const harness = mount(`${PAGE}?showcase=listing_fragments%3D0`, {
            ...CONFIG,
            signature: "listing_fragments=0",
        });

        expect(harness.replaceState).toHaveBeenCalledWith({}, "", PAGE);
        expect(document.cookie).toContain("obsidian_showcase=");
        harness.teardown();
    });

    it("leaves a plain url alone", () => {
        const harness = mount();

        expect(harness.replaceState).not.toHaveBeenCalled();
        harness.teardown();
    });

    it("does nothing at all when the panel is not on the page", () => {
        document.body.innerHTML = "";

        expect(() => bindShowcasePanel(CONFIG, { location: { href: PAGE, assign: vi.fn() } })()).not.toThrow();
    });
});
