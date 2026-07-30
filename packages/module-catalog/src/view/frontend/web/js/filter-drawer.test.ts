import { beforeEach, describe, expect, it, vi } from "vitest";
import { bindFilterDrawer } from "MageObsidian_Catalog::js/filter-drawer";

const markup = (): string => `
    <button type="button" data-filter-open aria-expanded="false">Filters</button>
    <dialog data-filter-dialog>
        <button type="button" data-filter-close>Close</button>
        <nav class="ln">
            <details class="ln__filter" open><summary>Category</summary></details>
            <details class="ln__filter" open><summary>Price</summary></details>
        </nav>
    </dialog>
`;

const render = () => {
    document.body.innerHTML = markup();
    const dialog = document.querySelector<HTMLDialogElement>("[data-filter-dialog]")!;
    // happy-dom leaves showModal unimplemented; the attribute is what the CSS and
    // the enhancer both read, so standing it in keeps the assertions about behaviour.
    if (typeof dialog.showModal !== "function") {
        dialog.showModal = () => dialog.setAttribute("open", "");
    }
    if (typeof dialog.close !== "function") {
        dialog.close = () => {
            dialog.removeAttribute("open");
            dialog.dispatchEvent(new Event("close"));
        };
    }

    return {
        dialog,
        trigger: document.querySelector<HTMLElement>("[data-filter-open]")!,
        closer: document.querySelector<HTMLElement>("[data-filter-close]")!,
        groups: [...document.querySelectorAll<HTMLDetailsElement>(".ln__filter")],
    };
};

const deps = (compact = true) => ({
    lock: vi.fn(),
    unlock: vi.fn(),
    isCompact: () => compact,
});

describe("bindFilterDrawer", () => {
    beforeEach(() => {
        document.body.innerHTML = "";
    });

    it("does nothing when the page has no filter dialog", () => {
        document.body.innerHTML = '<button data-filter-open></button>';
        expect(() => bindFilterDrawer(document, deps())).not.toThrow();
    });

    it("opens the dialog and locks the page scroll", () => {
        const { dialog, trigger } = render();
        const d = deps();
        bindFilterDrawer(document, d);

        trigger.click();

        expect(dialog.hasAttribute("open")).toBe(true);
        expect(d.lock).toHaveBeenCalledTimes(1);
        expect(trigger.getAttribute("aria-expanded")).toBe("true");
    });

    it("stays shut on desktop, where the sidebar is already visible", () => {
        const { dialog, trigger } = render();
        const d = deps(false);
        bindFilterDrawer(document, d);

        trigger.click();

        expect(dialog.hasAttribute("open")).toBe(false);
        expect(d.lock).not.toHaveBeenCalled();
    });

    it("releases the scroll lock whenever the dialog closes", () => {
        const { dialog, trigger, closer } = render();
        const d = deps();
        bindFilterDrawer(document, d);

        trigger.click();
        closer.click();

        expect(dialog.hasAttribute("open")).toBe(false);
        expect(d.unlock).toHaveBeenCalledTimes(1);
        expect(trigger.getAttribute("aria-expanded")).toBe("false");
    });

    it("releases the scroll lock when the dialog closes on its own (Escape)", () => {
        const { dialog, trigger } = render();
        const d = deps();
        bindFilterDrawer(document, d);

        trigger.click();
        dialog.close();

        expect(d.unlock).toHaveBeenCalledTimes(1);
    });

    it("closes on a click that lands on the backdrop", () => {
        const { dialog, trigger, closer } = render();
        bindFilterDrawer(document, deps());

        trigger.click();
        closer.dispatchEvent(new Event("click", { bubbles: true }));
        expect(dialog.hasAttribute("open")).toBe(false);

        trigger.click();
        expect(dialog.hasAttribute("open")).toBe(true);
        dialog.dispatchEvent(new Event("click", { bubbles: true }));
        expect(dialog.hasAttribute("open")).toBe(false);
    });

    it("collapses every group on first open", () => {
        const { trigger, groups } = render();
        bindFilterDrawer(document, deps());

        trigger.click();

        expect(groups.every((group) => !group.hasAttribute("open"))).toBe(true);
    });

    it("leaves the groups alone on later opens", () => {
        const { dialog, trigger, groups } = render();
        bindFilterDrawer(document, deps());

        trigger.click();
        dialog.close();
        groups[1].setAttribute("open", "");
        trigger.click();

        expect(groups[1].hasAttribute("open")).toBe(true);
    });

    it("closes an open drawer when the viewport grows past the breakpoint", () => {
        const { dialog, trigger } = render();
        let compact = true;
        bindFilterDrawer(document, {
            lock: vi.fn(),
            unlock: vi.fn(),
            isCompact: () => compact,
        });

        trigger.click();
        expect(dialog.hasAttribute("open")).toBe(true);

        compact = false;
        window.dispatchEvent(new Event("resize"));

        expect(dialog.hasAttribute("open")).toBe(false);
    });

    it("unbinds every listener it added", () => {
        const { dialog, trigger } = render();
        const teardown = bindFilterDrawer(document, deps());

        teardown();
        trigger.click();

        expect(dialog.hasAttribute("open")).toBe(false);
    });
});
