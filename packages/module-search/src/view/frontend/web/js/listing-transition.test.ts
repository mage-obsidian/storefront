import { beforeEach, describe, expect, it, vi } from "vitest";
import {
    CardRole,
    SWAP_CLASS,
    cardNames,
    createListingTransition,
    incomingCardNames,
    tagCards,
    type ViewTransitionLike,
} from "MageObsidian_Search::js/listing-transition";

const card = (name: string, id = name): string =>
    `<style>#${id}{view-transition-name: ${name}; view-transition-class: obsidian-card}</style>` +
    `<li class="product-item" id="${id}"></li>`;

const styleOf = (id: string): string =>
    document.getElementById(id)!.style.getPropertyValue("view-transition-class");

const classOf = (id: string): string =>
    getComputedStyle(document.getElementById(id)!).getPropertyValue("view-transition-class");

const nameOf = (id: string): string =>
    document.getElementById(id)!.style.getPropertyValue("view-transition-name");

interface FakeTransition extends ViewTransitionLike {
    settle: () => void;
    fail: (reason: unknown) => void;
}

const fakeTransition = (): { start: (update: () => void) => FakeTransition; last: () => FakeTransition } => {
    let latest: FakeTransition;

    const start = (update: () => void): FakeTransition => {
        let done: () => void;
        let broke: (reason: unknown) => void;
        const finished = new Promise<void>((resolve, reject) => {
            done = resolve;
            broke = reject;
        });
        let updateCallbackDone = Promise.resolve();
        try {
            update();
        } catch (error) {
            updateCallbackDone = Promise.reject(error);
        }

        latest = {
            updateCallbackDone,
            ready: Promise.resolve(),
            finished,
            settle: () => done(),
            fail: (reason: unknown) => broke(reason),
        };

        return latest;
    };

    return { start, last: () => latest };
};

const withStarter = (start: unknown): Document => {
    Object.defineProperty(document, "startViewTransition", {
        configurable: true,
        writable: true,
        value: start,
    });

    return document;
};

beforeEach(() => {
    document.body.innerHTML = "";
    document.documentElement.className = "";
    Reflect.deleteProperty(document, "startViewTransition");
});

describe("cardNames", () => {
    it("collects every named card", () => {
        document.body.innerHTML = card("product-1") + card("product-2");

        expect([...cardNames(document)]).toEqual(["product-1", "product-2"]);
    });

    it("ignores cards the theme did not name", () => {
        document.body.innerHTML = '<li class="product-item" id="bare"></li>' + card("product-1");

        expect([...cardNames(document)]).toEqual(["product-1"]);
    });

    // A repeated name aborts the whole transition, so the duplicate loses its
    // name rather than the listing losing its animation.
    it("strips a repeated name and keeps the first", () => {
        document.body.innerHTML = card("product-1", "first") + card("product-1", "second");

        expect([...cardNames(document)]).toEqual(["product-1"]);
        expect(nameOf("first")).toBe("");
        expect(nameOf("second")).toBe("none");
    });
});

describe("tagCards", () => {
    it("tags only the cards missing from the surviving set", () => {
        document.body.innerHTML = card("product-1", "stays") + card("product-2", "goes");

        expect(tagCards(document, new Set(["product-1"]), CardRole.Exit)).toBe(1);
        expect(styleOf("stays")).toBe("");
        expect(classOf("stays")).toBe("obsidian-card");
        expect(styleOf("goes")).toBe("obsidian-card obsidian-card-exit");
    });

    it("keeps the shared class alongside the role", () => {
        document.body.innerHTML = card("product-9", "fresh");

        tagCards(document, new Set(), CardRole.Enter);

        expect(styleOf("fresh")).toBe("obsidian-card obsidian-card-enter");
    });

    it("leaves unnamed cards alone", () => {
        document.body.innerHTML = '<li class="product-item" id="bare"></li>';

        expect(tagCards(document, new Set(), CardRole.Exit)).toBe(0);
        expect(styleOf("bare")).toBe("");
    });
});

describe("incomingCardNames", () => {
    it("reads the names the fragment declares without touching the page", () => {
        document.body.innerHTML = card("product-1", "live");

        const names = incomingCardNames(document, {
            listing: `<div>${card("product-2")}${card("product-3")}</div>`,
        });

        expect([...names].sort()).toEqual(["product-2", "product-3"]);
        expect(document.getElementById("live")).not.toBeNull();
    });

    it("survives fragment html with no cards in it", () => {
        expect(incomingCardNames(document, { listing: "<div>empty</div>" }).size).toBe(0);
    });

    it("reads a fragment whose names live in one block for the whole grid", () => {
        const names = incomingCardNames(document, {
            listing:
                "<div><style>.product-item--7{view-transition-name: product-7}" +
                ".product-item--8{view-transition-name: product-8}</style>" +
                '<li class="product-item product-item--7"></li>' +
                '<li class="product-item product-item--8"></li></div>',
        });

        expect([...names].sort()).toEqual(["product-7", "product-8"]);
    });
});

describe("createListingTransition", () => {
    it("runs the update directly when the browser has no view transitions", async () => {
        const run = createListingTransition(withStarter(undefined));

        await expect(run(() => "swapped")).resolves.toBe("swapped");
        expect(document.documentElement.classList.contains(SWAP_CLASS)).toBe(false);
    });

    // The drawer is a modal <dialog>: the top layer lands in neither snapshot.
    it("runs the update directly while a modal dialog is open", async () => {
        document.body.innerHTML = "<dialog open></dialog>";
        const start = vi.fn();
        const run = createListingTransition(withStarter(start));

        await expect(run(() => "swapped")).resolves.toBe("swapped");
        expect(start).not.toHaveBeenCalled();
    });

    it("returns what the update produced and scopes the root while it runs", async () => {
        const fake = fakeTransition();
        const run = createListingTransition(withStarter(fake.start));

        const outcome = run(() => {
            expect(document.documentElement.classList.contains(SWAP_CLASS)).toBe(true);
            return ["listing"];
        });

        await expect(outcome).resolves.toEqual(["listing"]);
        expect(document.documentElement.classList.contains(SWAP_CLASS)).toBe(true);

        fake.last().settle();
        await Promise.resolve();
        expect(document.documentElement.classList.contains(SWAP_CLASS)).toBe(false);
    });

    it("keeps the scope until the last overlapping swap has finished", async () => {
        const fake = fakeTransition();
        const run = createListingTransition(withStarter(fake.start));

        await run(() => "first");
        const first = fake.last();
        await run(() => "second");
        const second = fake.last();

        first.settle();
        await Promise.resolve();
        expect(document.documentElement.classList.contains(SWAP_CLASS)).toBe(true);

        second.settle();
        await Promise.resolve();
        expect(document.documentElement.classList.contains(SWAP_CLASS)).toBe(false);
    });

    it("propagates a failed swap and lets go of the root", async () => {
        const fake = fakeTransition();
        const run = createListingTransition(withStarter(fake.start));
        const boom = new Error("no target for that section");

        const outcome = run(() => {
            throw boom;
        });

        await expect(outcome).rejects.toBe(boom);

        fake.last().fail(boom);
        await Promise.resolve();
        expect(document.documentElement.classList.contains(SWAP_CLASS)).toBe(false);
    });
});
