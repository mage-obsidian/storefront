import { beforeEach, describe, expect, it, vi } from "vitest";
import events from "MageObsidian_ModernFrontend::js/events";
import { listingEvent } from "MageObsidian_Storefront::js/listing-events";
import { MutationPhase } from "mage-obsidian/runtime/mutationEvent.ts";
import {
    bindListingNavigator,
    readConfig,
    type NavigatorConfig,
    type NavigatorDeps,
} from "MageObsidian_Search::js/listing-navigator";

const PAGE = "https://shop.test/men/tops-men.html";
const CONFIG: NavigatorConfig = {
    parameter: "obsidian_fragment",
    attribute: "data-obsidian-section",
    paths: ["/men/tops-men.html"],
};

const LISTING = `
    <div data-obsidian-section="filters">
        <a id="filter" href="${PAGE}?color=59">Blue</a>
    </div>
    <div data-obsidian-section="listing">
        <a id="page-two" href="${PAGE}?p=2">2</a>
        <a id="product" href="https://shop.test/some-product.html">A product</a>
        <select id="sorter" data-nav-select>
            <option value="${PAGE}?product_list_order=price" selected></option>
        </select>
    </div>`;

const jsonResponse = (body: unknown, ok = true, status = 200) =>
    ({
        ok,
        status,
        headers: { get: () => "application/json" },
        json: async () => body,
    }) as unknown as Response;

const sections = { listing: '<div data-obsidian-section="listing">fresh listing</div>' };

interface Harness {
    fetch: ReturnType<typeof vi.fn>;
    pushState: ReturnType<typeof vi.fn>;
    assign: ReturnType<typeof vi.fn>;
    teardown: () => void;
}

const mount = (
    response: unknown = jsonResponse({ sections }),
    extra: Partial<NavigatorDeps> = {},
): Harness => {
    const fetchMock = vi.fn().mockResolvedValue(response);
    const pushState = vi.fn();
    const assign = vi.fn();

    const teardown = bindListingNavigator(CONFIG, {
        fetch: fetchMock as unknown as typeof fetch,
        history: { pushState },
        location: { href: PAGE, assign },
        ...extra,
    });

    return { fetch: fetchMock, pushState, assign, teardown };
};

const click = (id: string, init: MouseEventInit = {}): void => {
    document
        .getElementById(id)!
        .dispatchEvent(new MouseEvent("click", { bubbles: true, cancelable: true, ...init }));
};

const settle = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));

describe("readConfig", () => {
    beforeEach(() => {
        document.body.innerHTML = "";
    });

    it("returns nothing when the page did not boot the navigator", () => {
        expect(readConfig()).toBeNull();
    });

    it("reads the names PHP published", () => {
        document.body.innerHTML =
            '<div data-obsidian-listing data-parameter="obsidian_fragment"' +
            ' data-attribute="data-obsidian-section" data-paths="/men/tops-men.html"></div>';

        expect(readConfig()).toEqual(CONFIG);
    });

    it("returns nothing when the server published no listing path", () => {
        document.body.innerHTML =
            '<div data-obsidian-listing data-parameter="obsidian_fragment"' +
            ' data-attribute="data-obsidian-section" data-paths=""></div>';

        expect(readConfig()).toBeNull();
    });
});

describe("bindListingNavigator", () => {
    let harness: Harness;

    beforeEach(() => {
        events.reset();
        document.body.innerHTML = LISTING;
    });

    const teardown = (): void => harness?.teardown();

    it("asks for the fragment with a canonical query and swaps the sections in", async () => {
        harness = mount();

        click("filter");
        await settle();

        expect(harness.fetch).toHaveBeenCalledTimes(1);
        expect(harness.fetch.mock.calls[0][0]).toBe(
            `${PAGE}?color=59&obsidian_fragment=1`,
        );
        expect(document.querySelector('[data-obsidian-section="listing"]')?.textContent)
            .toBe("fresh listing");
        expect(harness.pushState).toHaveBeenCalledWith({}, "", `${PAGE}?color=59`);
        teardown();
    });

    it("sorts the query so one set of filters is one cache object", async () => {
        harness = mount();
        document.getElementById("filter")!.setAttribute("href", `${PAGE}?p=2&color=59`);

        click("filter");
        await settle();

        expect(harness.fetch.mock.calls[0][0]).toBe(`${PAGE}?color=59&obsidian_fragment=1&p=2`);
        teardown();
    });

    it("announces the exchange", async () => {
        harness = mount();

        click("page-two");
        await settle();

        expect(events.recorded(listingEvent(MutationPhase.Before))).toHaveLength(1);
        expect(events.recorded(listingEvent(MutationPhase.After))).toEqual([
            expect.objectContaining({ url: `${PAGE}?p=2`, result: ["listing"] }),
        ]);
        teardown();
    });

    it("follows a control pointing at another declared listing path", async () => {
        harness = mount();
        const other = "https://shop.test/catalogsearch/result/index/?q=jacket";
        document.getElementById("filter")!.setAttribute("href", other);
        harness.teardown();
        harness = {
            ...harness,
            teardown: bindListingNavigator(
                { ...CONFIG, paths: [...CONFIG.paths, "/catalogsearch/result/index/"] },
                {
                    fetch: harness.fetch as unknown as typeof fetch,
                    history: { pushState: harness.pushState },
                    location: { href: PAGE, assign: harness.assign },
                },
            ),
        };

        click("filter");
        await settle();

        expect(harness.fetch.mock.calls[0][0]).toBe(
            "https://shop.test/catalogsearch/result/index/?obsidian_fragment=1&q=jacket",
        );
        teardown();
    });

    it("leaves a product link alone: it goes somewhere else", async () => {
        harness = mount();

        click("product");
        await settle();

        expect(harness.fetch).not.toHaveBeenCalled();
        teardown();
    });

    it("leaves a modified click alone so open-in-new-tab keeps working", async () => {
        harness = mount();

        click("page-two", { metaKey: true });
        await settle();

        expect(harness.fetch).not.toHaveBeenCalled();
        teardown();
    });

    it("takes over the sort select before the shared enhancer navigates", async () => {
        harness = mount();
        const select = document.getElementById("sorter") as HTMLSelectElement;
        const enhancer = vi.fn();
        select.addEventListener("change", enhancer);

        select.dispatchEvent(new Event("change", { bubbles: true }));
        await settle();

        expect(enhancer).not.toHaveBeenCalled();
        expect(harness.fetch.mock.calls[0][0]).toBe(
            `${PAGE}?obsidian_fragment=1&product_list_order=price`,
        );
        teardown();
    });

    it("falls back to a full navigation when the endpoint fails", async () => {
        harness = mount(jsonResponse({ error: true }, false, 500));

        click("page-two");
        await settle();

        expect(harness.assign).toHaveBeenCalledWith(`${PAGE}?p=2`);
        expect(harness.pushState).not.toHaveBeenCalled();
        expect(events.recorded(listingEvent(MutationPhase.Failed))).toHaveLength(1);
        teardown();
    });

    it("swaps nothing at all when one section has no target in the page", async () => {
        harness = mount(
            jsonResponse({ sections: { ...sections, ghost: "<div>nowhere</div>" } }),
        );

        click("page-two");
        await settle();

        expect(document.querySelector('[data-obsidian-section="listing"]')?.textContent)
            .not.toBe("fresh listing");
        expect(harness.assign).toHaveBeenCalledWith(`${PAGE}?p=2`);
        teardown();
    });

    it("discards a slow answer that a newer click already replaced", async () => {
        let releaseFirst: (value: Response) => void = () => {};
        const first = new Promise<Response>((resolve) => {
            releaseFirst = resolve;
        });
        const fetchMock = vi
            .fn()
            .mockReturnValueOnce(first)
            .mockResolvedValueOnce(
                jsonResponse({ sections: { listing: '<div data-obsidian-section="listing">second</div>' } }),
            );
        const pushState = vi.fn();
        harness = {
            fetch: fetchMock,
            pushState,
            assign: vi.fn(),
            teardown: bindListingNavigator(CONFIG, {
                fetch: fetchMock as unknown as typeof fetch,
                history: { pushState },
                location: { href: PAGE, assign: vi.fn() },
            }),
        };

        click("filter");
        click("page-two");
        await settle();
        releaseFirst(jsonResponse({ sections }));
        await settle();

        expect(document.querySelector('[data-obsidian-section="listing"]')?.textContent).toBe("second");
        expect(pushState).toHaveBeenCalledTimes(1);
        teardown();
    });

    it("puts the swap inside the transition and pushes only once it applied", async () => {
        const order: string[] = [];
        const pushState = vi.fn(() => order.push("push"));
        const transition = vi.fn(async (update: () => unknown) => {
            order.push("open");
            const outcome = update();
            order.push("close");
            return outcome;
        });
        harness = mount(jsonResponse({ sections }), {
            history: { pushState },
            transition: transition as never,
        });

        click("filter");
        await settle();

        expect(order).toEqual(["open", "close", "push"]);
        teardown();
    });

    it("tags the cards by which side of the swap they were on", async () => {
        const marked = (id: string): string =>
            document.getElementById(id)!.style.getPropertyValue("view-transition-class");
        const cards = (ids: string[]): string =>
            ids
                .map(
                    (id) =>
                        `<li class="product-item" id="${id}"` +
                        ` style="view-transition-name: product-${id}; view-transition-class: obsidian-card"></li>`,
                )
                .join("");

        document.body.innerHTML = LISTING;
        document.querySelector('[data-obsidian-section="listing"]')!.innerHTML +=
            `<ol>${cards(["1", "2"])}</ol>`;
        harness = mount(
            jsonResponse({
                sections: {
                    listing: `<div data-obsidian-section="listing"><ol>${cards(["1", "3"])}</ol></div>`,
                },
            }),
        );

        click("filter");
        await settle();

        // 2 left with the old DOM, so its tag is only observable on the snapshot
        // the browser had already taken; 1 survived and 3 arrived.
        expect(marked("1")).toBe("obsidian-card");
        expect(marked("3")).toBe("obsidian-card obsidian-card-enter");
        expect(document.getElementById("2")).toBeNull();
        teardown();
    });
});
