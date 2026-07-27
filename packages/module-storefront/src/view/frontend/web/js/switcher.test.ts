import { describe, it, expect, vi, beforeEach } from "vitest";
import { isPlainClick, resolveSwitchTarget, bindSwitchers, type SwitchDeps } from "./switcher.ts";

function makeSwitcher(urls: string[]): HTMLElement {
    const root = document.createElement("div");
    root.innerHTML = `
        <details data-switcher>
            <summary>USD</summary>
            <ul>
                ${urls.map((url) => `<li><a href="${url}" data-switch-link>x</a></li>`).join("")}
            </ul>
        </details>
        <button id="outside">outside</button>
    `;
    document.body.append(root);
    return root;
}

function makeDeps(responseUrl = ""): SwitchDeps & { calls: string[] } {
    const calls: string[] = [];
    return {
        calls,
        fetch: vi.fn(async (url: string) => {
            calls.push(`fetch:${url}`);
            return { url: responseUrl };
        }),
        currentUrl: () => "https://shop.test/current",
        assign: (url: string) => calls.push(`assign:${url}`),
        reload: () => calls.push("reload"),
    };
}

const click = (el: Element): void => {
    el.dispatchEvent(new MouseEvent("click", { bubbles: true, button: 0 }));
};

beforeEach(() => {
    document.body.innerHTML = "";
});

describe("isPlainClick", () => {
    it("accepts an unmodified primary click", () => {
        expect(isPlainClick(new MouseEvent("click", { button: 0 }))).toBe(true);
    });

    it("rejects middle clicks and modified clicks", () => {
        expect(isPlainClick(new MouseEvent("click", { button: 1 }))).toBe(false);
        for (const modifier of ["metaKey", "ctrlKey", "shiftKey", "altKey"]) {
            expect(isPlainClick(new MouseEvent("click", { button: 0, [modifier]: true }))).toBe(false);
        }
    });
});

describe("resolveSwitchTarget", () => {
    it("assigns when the redirect landed somewhere else", () => {
        expect(resolveSwitchTarget("https://shop.test/es", "https://shop.test/current")).toEqual({
            action: "assign",
            url: "https://shop.test/es",
        });
    });

    it("reloads when the redirect came back to the same URL", () => {
        expect(resolveSwitchTarget("https://shop.test/current", "https://shop.test/current")).toEqual({
            action: "reload",
        });
    });

    it("reloads when the response exposes no URL", () => {
        expect(resolveSwitchTarget("", "https://shop.test/current")).toEqual({ action: "reload" });
    });
});

describe("bindSwitchers", () => {
    it("intercepts a switch link, closes the panel and reloads", async () => {
        const root = makeSwitcher(["https://shop.test/switch/eur"]);
        const deps = makeDeps("https://shop.test/current");
        bindSwitchers(document, deps);

        const details = root.querySelector<HTMLDetailsElement>("details")!;
        details.open = true;
        click(root.querySelector("a[data-switch-link]")!);
        await vi.waitFor(() => expect(deps.calls).toContain("reload"));

        expect(deps.calls[0]).toBe("fetch:https://shop.test/switch/eur");
        expect(details.open).toBe(false);
    });

    it("assigns the redirect target when it differs from the current URL", async () => {
        makeSwitcher(["https://shop.test/switch/es"]);
        const deps = makeDeps("https://shop.test/es/home");
        bindSwitchers(document, deps);

        click(document.querySelector("a[data-switch-link]")!);
        await vi.waitFor(() => expect(deps.calls).toContain("assign:https://shop.test/es/home"));
    });

    it("falls back to the plain link when the fetch fails", async () => {
        makeSwitcher(["https://shop.test/switch/eur"]);
        const deps = makeDeps();
        deps.fetch = vi.fn(async () => {
            throw new Error("offline");
        });
        bindSwitchers(document, deps);

        click(document.querySelector("a[data-switch-link]")!);
        await vi.waitFor(() =>
            expect(deps.calls).toContain("assign:https://shop.test/switch/eur"),
        );
    });

    it("leaves modified clicks to the browser", () => {
        makeSwitcher(["https://shop.test/switch/eur"]);
        const deps = makeDeps();
        bindSwitchers(document, deps);

        document
            .querySelector("a[data-switch-link]")!
            .dispatchEvent(new MouseEvent("click", { bubbles: true, button: 0, metaKey: true }));

        expect(deps.calls).toEqual([]);
    });

    it("closes an open panel when clicking outside it", () => {
        const root = makeSwitcher(["https://shop.test/switch/eur"]);
        bindSwitchers(document, makeDeps());

        const details = root.querySelector<HTMLDetailsElement>("details")!;
        details.open = true;
        click(root.querySelector("#outside")!);

        expect(details.open).toBe(false);
    });

    it("closes on Escape and returns focus to the summary", () => {
        const root = makeSwitcher(["https://shop.test/switch/eur"]);
        bindSwitchers(document, makeDeps());

        const details = root.querySelector<HTMLDetailsElement>("details")!;
        details.open = true;
        const link = root.querySelector<HTMLAnchorElement>("a[data-switch-link]")!;
        link.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape", bubbles: true }));

        expect(details.open).toBe(false);
        expect(document.activeElement).toBe(root.querySelector("summary"));
    });
});
