import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import {
    classifyNavigation,
    markProductHero,
    dedupeCardNames,
    clearCardNames,
    bindViewTransitions,
    silenceTransition,
} from "MageObsidian_Storefront::js/viewTransitions";

const LISTING = "https://shop.test/women/tops.html";
const PRODUCT = "https://shop.test/blue-shirt.html";

const nameOf = (el: HTMLElement): string => el.style.getPropertyValue("view-transition-name");

const facts = (over: Partial<Parameters<typeof classifyNavigation>[0]> = {}) => ({
    trigger: null,
    from: LISTING,
    to: PRODUCT,
    isListingPage: false,
    navigationType: "push",
    ...over,
});

describe("classifyNavigation", () => {
    it("animates a click on a product card", () => {
        document.body.innerHTML = `<article class="product-card"><a id="t" href="${PRODUCT}"></a></article>`;
        const trigger = document.getElementById("t") as Element;

        expect(classifyNavigation(facts({ trigger }))).toBe("product");
    });

    it("animates pagination, sort and filters — same path, different query", () => {
        expect(classifyNavigation(facts({ to: `${LISTING}?p=2` }))).toBe("listing");
        expect(classifyNavigation(facts({ to: `${LISTING}?product_list_order=price` }))).toBe(
            "listing",
        );
        // Back to page one: the query goes away instead of changing.
        expect(classifyNavigation(facts({ from: `${LISTING}?p=2`, to: LISTING }))).toBe("listing");
    });

    it("animates a search toolbar link, which moves the path to .../result/index/", () => {
        document.body.innerHTML = `<div id="maincontent"><div class="toolbar"><a id="t" href="#"></a></div></div>`;

        expect(
            classifyNavigation(
                facts({
                    trigger: document.getElementById("t") as Element,
                    from: "https://shop.test/catalogsearch/result/?q=bag",
                    to: "https://shop.test/catalogsearch/result/index/?product_list_dir=desc&q=bag",
                    isListingPage: true,
                }),
            ),
        ).toBe("listing");
    });

    it("leaves the listing alone when the link sits outside its content well", () => {
        document.body.innerHTML = `<header><a id="t" href="/"></a></header><div id="maincontent"></div>`;

        expect(
            classifyNavigation(
                facts({
                    trigger: document.getElementById("t") as Element,
                    to: "https://shop.test/",
                    isListingPage: true,
                }),
            ),
        ).toBeNull();
    });

    it("animates a subcategory tile", () => {
        document.body.innerHTML = `<a id="t" class="subcategory-card" href="https://shop.test/women/skirts.html"></a>`;
        const trigger = document.getElementById("t") as Element;

        expect(
            classifyNavigation(facts({ trigger, to: "https://shop.test/women/skirts.html" })),
        ).toBe("listing");
    });

    it("skips the navigation that washed the page grey: listing to home", () => {
        document.body.innerHTML = `<a id="t" href="https://shop.test/"></a>`;
        const trigger = document.getElementById("t") as Element;

        expect(classifyNavigation(facts({ trigger, to: "https://shop.test/" }))).toBeNull();
    });

    it("skips header nav, cart and any other cross-section move", () => {
        expect(classifyNavigation(facts({ to: "https://shop.test/checkout/cart/" }))).toBeNull();
        expect(classifyNavigation(facts({ to: "https://shop.test/customer/account/" }))).toBeNull();
    });

    it("skips a different origin and a reload", () => {
        expect(classifyNavigation(facts({ to: "https://other.test/x.html" }))).toBeNull();
        expect(classifyNavigation(facts({ to: LISTING, from: LISTING }))).toBeNull();
    });

    it("skips back and forward, which restore the destination before the transition runs", () => {
        document.body.innerHTML = `<article class="product-card"><a id="t" href="${PRODUCT}"></a></article>`;
        const trigger = document.getElementById("t") as Element;

        expect(
            classifyNavigation(facts({ from: PRODUCT, to: LISTING, navigationType: "traverse" })),
        ).toBeNull();
        expect(
            classifyNavigation(facts({ to: `${LISTING}?p=2`, navigationType: "traverse" })),
        ).toBeNull();
        // Even a card click is skipped when the browser is traversing history.
        expect(classifyNavigation(facts({ trigger, navigationType: "traverse" }))).toBeNull();
    });

    it("survives a malformed destination", () => {
        expect(classifyNavigation(facts({ to: "" }))).toBeNull();
        expect(classifyNavigation(facts({ to: ":::" }))).toBeNull();
    });
});

describe("markProductHero", () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <article class="product-card">
                <a id="t" class="product-card__media" href="${PRODUCT}"><img id="card-img"></a>
            </article>`;
    });

    afterEach(() => {
        document.body.innerHTML = "";
    });

    it("names the clicked card's media box, not the image inside it", () => {
        const media = markProductHero(document.getElementById("t") as Element, document);

        // The frame travels: naming the <img> would leave the PDP gallery box
        // behind at full size, showing its background as a grey mat.
        expect(media).toBe(document.getElementById("t"));
        expect(media!.style.viewTransitionName).toBe("pdp-hero");
        expect(document.getElementById("card-img")!.style.viewTransitionName).toBeFalsy();
    });

    it("clears a name it left on a previous card", () => {
        const first = markProductHero(document.getElementById("t") as Element, document);
        document.body.insertAdjacentHTML(
            "beforeend",
            `<article class="product-card"><a id="t2" class="product-card__media" href="${PRODUCT}"><img id="img2"></a></article>`,
        );

        const second = markProductHero(document.getElementById("t2") as Element, document);

        expect(second!.style.viewTransitionName).toBe("pdp-hero");
        expect(first!.style.viewTransitionName).toBe("");
    });

    it("releases the gallery's name, which the PDP stylesheet also sets", () => {
        document.body.insertAdjacentHTML("beforeend", `<figure class="pdp__gallery-main" id="hero"><img></figure>`);

        markProductHero(document.getElementById("t") as Element, document);

        expect(document.getElementById("hero")!.style.viewTransitionName).toBe("none");
    });

    it("returns null for a card with no media box", () => {
        document.body.innerHTML = `<article class="product-card"><a id="t" href="${PRODUCT}"></a></article>`;

        expect(markProductHero(document.getElementById("t") as Element, document)).toBeNull();
    });
});

describe("card names", () => {
    const grid = (...names: string[]) => {
        document.body.innerHTML = `<ol>${names
            .map((name, at) => `<li class="product-item" id="c${at}" style="view-transition-name:${name}"></li>`)
            .join("")}</ol>`;
        return names.map((_, at) => document.getElementById(`c${at}`) as HTMLElement);
    };

    afterEach(() => {
        document.body.innerHTML = "";
    });

    it("keeps the first of a repeated name and releases the rest", () => {
        const [first, second, other] = grid("product-1", "product-1", "product-2");

        expect(dedupeCardNames(document)).toBe(1);
        expect(nameOf(first)).toBe("product-1");
        expect(nameOf(second)).toBe("");
        expect(nameOf(other)).toBe("product-2");
    });

    it("leaves a grid of unique names untouched", () => {
        const cards = grid("product-1", "product-2", "product-3");

        expect(dedupeCardNames(document)).toBe(0);
        expect(cards.map(nameOf)).toEqual([
            "product-1",
            "product-2",
            "product-3",
        ]);
    });

    it("releases every card at once", () => {
        const cards = grid("product-1", "product-2");

        clearCardNames(document);

        expect(cards.every((c) => nameOf(c) === "")).toBe(true);
    });
});

describe("bindViewTransitions", () => {
    const dispatchSwap = (url: string, navigationType = "push") => {
        const transition = { skipTransition: vi.fn() };
        const event = Object.assign(new Event("pageswap"), {
            viewTransition: transition,
            activation: { entry: { url }, navigationType },
        });
        window.dispatchEvent(event);

        return transition;
    };

    let stop: () => void;

    beforeEach(() => {
        // The binder reads the current location as the origin of the navigation,
        // so the document has to actually sit on the listing.
        (window as unknown as { happyDOM: { setURL: (url: string) => void } }).happyDOM.setURL(
            LISTING,
        );
        document.body.innerHTML = `
            <article class="product-card">
                <a id="card" class="product-card__media" href="${PRODUCT}"><img id="card-img"></a>
            </article>
            <a id="home" href="https://shop.test/">Home</a>`;
        stop = bindViewTransitions(window);
    });

    afterEach(() => {
        stop();
        document.body.innerHTML = "";
    });

    it("skips the transition and leaves no name behind on an unrelated navigation", () => {
        (document.getElementById("home") as HTMLElement).click();
        const transition = dispatchSwap("https://shop.test/");

        expect(transition.skipTransition).toHaveBeenCalledOnce();
        expect(document.getElementById("card")!.style.viewTransitionName).toBeFalsy();
    });

    it("lets a card click through and names its image", () => {
        (document.getElementById("card-img") as HTMLElement).click();
        const transition = dispatchSwap(PRODUCT);

        expect(transition.skipTransition).not.toHaveBeenCalled();
        expect(document.getElementById("card")!.style.viewTransitionName).toBe("pdp-hero");
    });

    it("strips the grid's card names on the way to a product page", () => {
        document.body.insertAdjacentHTML(
            "beforeend",
            `<li class="product-item" id="sibling" style="view-transition-name:product-9"></li>`,
        );

        (document.getElementById("card-img") as HTMLElement).click();
        dispatchSwap(PRODUCT);

        expect(nameOf(document.getElementById("sibling") as HTMLElement)).toBe("");
    });

    it("keeps the card names of a listing move, which is what reorders them", () => {
        document.body.insertAdjacentHTML(
            "beforeend",
            `<li class="product-item" id="kept" style="view-transition-name:product-9"></li>`,
        );

        const transition = dispatchSwap(`${LISTING}?product_list_order=name`);

        expect(transition.skipTransition).not.toHaveBeenCalled();
        expect(nameOf(document.getElementById("kept") as HTMLElement)).toBe("product-9");
    });

    it("skips a navigation with no click behind it that leaves the catalog", () => {
        const transition = dispatchSwap("https://shop.test/customer/account/");

        expect(transition.skipTransition).toHaveBeenCalledOnce();
    });

    it("skips the back button and leaves no name on the card it would have morphed", () => {
        (document.getElementById("card-img") as HTMLElement).click();
        const transition = dispatchSwap(PRODUCT, "traverse");

        expect(transition.skipTransition).toHaveBeenCalledOnce();
        expect(document.getElementById("card")!.style.viewTransitionName).toBeFalsy();
    });

    it("does nothing when the browser started no transition", () => {
        const event = Object.assign(new Event("pageswap"), {
            viewTransition: null,
            activation: { entry: { url: PRODUCT } },
        });

        expect(() => window.dispatchEvent(event)).not.toThrow();
    });

    it("stops listening once released", () => {
        stop();
        (document.getElementById("home") as HTMLElement).click();
        const transition = dispatchSwap("https://shop.test/");

        expect(transition.skipTransition).not.toHaveBeenCalled();
    });
});

describe("silenceTransition", () => {
    it("settles both promises so a skip raises no unhandled rejection", async () => {
        const transition = {
            skipTransition: vi.fn(),
            ready: Promise.reject(new Error("Transition was skipped")),
            finished: Promise.reject(new Error("Transition was skipped")),
        };

        silenceTransition(transition);
        await Promise.resolve();

        await expect(transition.ready).rejects.toThrow("Transition was skipped");
    });

    it("leaves a transition that runs untouched", async () => {
        const transition = { skipTransition: vi.fn(), ready: Promise.resolve() };

        expect(() => silenceTransition(transition)).not.toThrow();
        await expect(transition.ready).resolves.toBeUndefined();
    });

    it("tolerates a transition that exposes neither promise", () => {
        expect(() => silenceTransition({ skipTransition: vi.fn() })).not.toThrow();
    });
});

describe("bindViewTransitions rejection handling", () => {
    let stop: () => void;

    beforeEach(() => {
        (window as unknown as { happyDOM: { setURL: (url: string) => void } }).happyDOM.setURL(
            LISTING,
        );
        document.body.innerHTML = `<a id="home" href="https://shop.test/">Home</a>`;
        stop = bindViewTransitions(window);
    });

    afterEach(() => {
        stop();
        document.body.innerHTML = "";
    });

    const spied = () => ({
        skipTransition: vi.fn(),
        ready: { catch: vi.fn() },
        finished: { catch: vi.fn() },
    });

    it("attaches handlers when it skips an unrelated navigation", () => {
        (document.getElementById("home") as HTMLElement).click();
        const transition = spied();
        window.dispatchEvent(
            Object.assign(new Event("pageswap"), {
                viewTransition: transition,
                activation: { entry: { url: "https://shop.test/" }, navigationType: "push" },
            }),
        );

        expect(transition.skipTransition).toHaveBeenCalledOnce();
        expect(transition.ready.catch).toHaveBeenCalledOnce();
        expect(transition.finished.catch).toHaveBeenCalledOnce();
    });

    it("settles the transition the incoming document is handed on a traversal", () => {
        const transition = spied();
        window.dispatchEvent(
            Object.assign(new Event("pagereveal"), { viewTransition: transition }),
        );

        expect(transition.ready.catch).toHaveBeenCalledOnce();
        expect(transition.finished.catch).toHaveBeenCalledOnce();
        expect(transition.skipTransition).not.toHaveBeenCalled();
    });

    it("ignores a reveal that carries no transition", () => {
        expect(() =>
            window.dispatchEvent(Object.assign(new Event("pagereveal"), { viewTransition: null })),
        ).not.toThrow();
    });
});
