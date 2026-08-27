import { beforeEach, describe, expect, it, vi } from "vitest";
import { armDeferredScripts, loadDeferredScript, whenReady } from "./deferred-scripts.ts";

const declare = (attributes: string): HTMLElement => {
    document.body.innerHTML = `<div data-deferred-script data-src="https://cdn.test/tag.js" ${attributes} hidden></div>`;
    return document.querySelector("[data-deferred-script]") as HTMLElement;
};

const loaded = (): string[] =>
    Array.from(document.head.querySelectorAll("script")).map((script) => script.getAttribute("src") ?? "");

beforeEach(() => {
    document.body.innerHTML = "";
    document.head.querySelectorAll("script").forEach((script) => script.remove());
});

describe("loadDeferredScript", () => {
    it("puts the script in the head, asynchronously", () => {
        const script = loadDeferredScript(declare(""));

        expect(script?.async).toBe(true);
        expect(loaded()).toEqual(["https://cdn.test/tag.js"]);
    });

    it("carries the attributes the declaration asked for", () => {
        const script = loadDeferredScript(declare(`data-attributes='{"crossorigin":"anonymous"}'`));

        expect(script?.getAttribute("crossorigin")).toBe("anonymous");
    });

    it("loads a script once however often it is asked", () => {
        const element = declare("");

        loadDeferredScript(element);
        loadDeferredScript(element);

        expect(loaded()).toHaveLength(1);
    });

    it("does nothing for a declaration with no source", () => {
        document.body.innerHTML = "<div data-deferred-script></div>";

        expect(loadDeferredScript(document.querySelector("[data-deferred-script]") as HTMLElement)).toBeNull();
        expect(loaded()).toHaveLength(0);
    });

    it("survives attributes that are not readable", () => {
        expect(() => loadDeferredScript(declare(`data-attributes="{oops"`))).not.toThrow();
        expect(loaded()).toHaveLength(1);
    });
});

describe("armDeferredScripts", () => {
    /**
     * The whole point: nothing third-party is on the critical path, so the
     * budget the storefront was measured against is the budget it keeps.
     */
    it("loads nothing until the shopper does something", () => {
        declare("");

        armDeferredScripts(document, window);

        expect(loaded()).toHaveLength(0);

        window.dispatchEvent(new Event("pointerdown"));

        expect(loaded()).toEqual(["https://cdn.test/tag.js"]);
    });

    it("answers any of the signals a real visitor produces", () => {
        for (const signal of ["keydown", "touchstart", "wheel", "scroll"]) {
            document.head.querySelectorAll("script").forEach((script) => script.remove());
            declare("");
            armDeferredScripts(document, window);

            window.dispatchEvent(new Event(signal));

            expect(loaded(), `nothing loaded on ${signal}`).toHaveLength(1);
        }
    });

    // A bypass has to be a visible decision, not an omission.
    it("loads a declared bypass straight away", () => {
        declare('data-until="now" data-reason="a purchase that waits is never reported"');

        armDeferredScripts(document, window);

        expect(loaded()).toEqual(["https://cdn.test/tag.js"]);
    });

    it("loads an idle declaration without waiting for the shopper", () => {
        vi.useFakeTimers();
        declare('data-until="idle"');

        armDeferredScripts(document, window);
        vi.runAllTimers();

        expect(loaded()).toHaveLength(1);
        vi.useRealTimers();
    });

    it("arms a declaration once", () => {
        declare("");

        armDeferredScripts(document, window);
        armDeferredScripts(document, window);
        window.dispatchEvent(new Event("pointerdown"));

        expect(loaded()).toHaveLength(1);
    });
});

describe("whenReady", () => {
    // The primitive a caller that composes its own URL schedules through, so the
    // waiting is written once.
    it("holds the caller until the shopper does something", () => {
        const run = vi.fn();

        whenReady("interaction", run, window);
        expect(run).not.toHaveBeenCalled();

        window.dispatchEvent(new Event("keydown"));
        expect(run).toHaveBeenCalledTimes(1);
    });

    it("runs a declared bypass immediately", () => {
        const run = vi.fn();

        whenReady("now", run, window);

        expect(run).toHaveBeenCalledTimes(1);
    });

    it("runs an idle caller without an interaction", () => {
        vi.useFakeTimers();
        const run = vi.fn();

        whenReady("idle", run, window);
        vi.runAllTimers();

        expect(run).toHaveBeenCalledTimes(1);
        vi.useRealTimers();
    });
});
