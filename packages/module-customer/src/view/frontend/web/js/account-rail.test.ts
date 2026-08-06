import { describe, it, expect, beforeEach, vi } from "vitest";
import { revealActiveRailLink } from "./account-rail.ts";

describe("revealActiveRailLink", () => {
    function buildRail(options: {
        scrollWidth: number;
        clientWidth: number;
        activeOffsetLeft?: number;
        activeOffsetWidth?: number;
        withActive?: boolean;
    }): { root: HTMLElement; list: HTMLElement; scrollTo: ReturnType<typeof vi.fn> } {
        document.body.innerHTML = `
            <aside class="account-rail">
                <ul class="account-rail__list">
                    <li><a class="account-rail__link" href="/a">A</a></li>
                    <li><a class="account-rail__link" href="/b"${options.withActive === false ? "" : ' aria-current="page"'}>B</a></li>
                </ul>
            </aside>`;

        const root = document.querySelector(".account-rail") as HTMLElement;
        const list = document.querySelector(".account-rail__list") as HTMLElement;
        const active = list.querySelector('[aria-current="page"]') as HTMLElement | null;

        // jsdom reports 0 for every layout metric; define the ones under test.
        Object.defineProperty(list, "scrollWidth", { value: options.scrollWidth });
        Object.defineProperty(list, "clientWidth", { value: options.clientWidth });
        if (active) {
            Object.defineProperty(active, "offsetLeft", { value: options.activeOffsetLeft ?? 0 });
            Object.defineProperty(active, "offsetWidth", { value: options.activeOffsetWidth ?? 0 });
        }

        const scrollTo = vi.fn();
        list.scrollTo = scrollTo;

        return { root, list, scrollTo };
    }

    beforeEach(() => {
        document.body.innerHTML = "";
    });

    it("centres the active link when the strip overflows", () => {
        const { root, scrollTo } = buildRail({
            scrollWidth: 900,
            clientWidth: 300,
            activeOffsetLeft: 400,
            activeOffsetWidth: 100,
        });

        revealActiveRailLink(root);

        expect(scrollTo).toHaveBeenCalledWith({ left: 300, behavior: "instant" });
    });

    it("does nothing when the rail is not scrollable", () => {
        const { root, scrollTo } = buildRail({ scrollWidth: 300, clientWidth: 300 });

        revealActiveRailLink(root);

        expect(scrollTo).not.toHaveBeenCalled();
    });

    it("does nothing when no entry is active", () => {
        const { root, scrollTo } = buildRail({
            scrollWidth: 900,
            clientWidth: 300,
            withActive: false,
        });

        revealActiveRailLink(root);

        expect(scrollTo).not.toHaveBeenCalled();
    });
});
