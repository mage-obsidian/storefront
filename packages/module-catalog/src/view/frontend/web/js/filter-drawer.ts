/**
 * Turns the layered-navigation sidebar into an off-canvas panel on narrow
 * viewports. The panel is a native <dialog>: `showModal` brings the focus trap,
 * Escape and inertness of the rest of the page with it, so none of that is
 * reimplemented here. Desktop never calls it — CSS keeps the same dialog laid out
 * as a plain sidebar.
 */
import events from "MageObsidian_ModernFrontend::js/events";
import { MutationPhase } from "mage-obsidian/runtime/mutationEvent.ts";
import { listingEvent } from "MageObsidian_Storefront::js/listing-events";
import { lockScroll, unlockScroll } from "MageObsidian_Storefront::js/scroll-lock";

const Hook = {
    Dialog: "data-filter-dialog",
    Trigger: "data-filter-open",
    Close: "data-filter-close",
} as const;

const FILTER_GROUP = ".ln__filter";
const OPEN_ATTRIBUTE = "open";
const EXPANDED_ATTRIBUTE = "aria-expanded";

export interface FilterDrawerDeps {
    lock?: () => void;
    unlock?: () => void;
    isCompact?: (trigger: HTMLElement) => boolean;
}

// The trigger carries the same breakpoint as the CSS, so its own visibility is the
// media query — there is no pixel value here to drift from the stylesheet.
const visible = (trigger: HTMLElement): boolean => trigger.offsetParent !== null;

export function bindFilterDrawer(root: ParentNode = document, deps: FilterDrawerDeps = {}): () => void {
    const dialog = root.querySelector<HTMLDialogElement>(`[${Hook.Dialog}]`);
    const trigger = root.querySelector<HTMLElement>(`[${Hook.Trigger}]`);
    if (!dialog || !trigger) {
        return () => {};
    }

    const lock = deps.lock ?? lockScroll;
    const unlock = deps.unlock ?? unlockScroll;
    const isCompact = deps.isCompact ?? visible;

    let collapsed = false;

    // Every group renders expanded so the desktop sidebar reads as one long list;
    // in a drawer that is several screens of scrolling. Collapsed once, on first
    // open, so a group the visitor opens afterwards stays open. Nothing is spared:
    // the core drops a filter from the sidebar once it is applied, so an expanded
    // group never carries the visitor's current selection.
    const collapseGroups = (): void => {
        if (collapsed) {
            return;
        }
        collapsed = true;
        dialog
            .querySelectorAll<HTMLDetailsElement>(FILTER_GROUP)
            .forEach((group) => group.removeAttribute(OPEN_ATTRIBUTE));
    };

    const open = (): void => {
        if (dialog.open || !isCompact(trigger)) {
            return;
        }
        collapseGroups();
        dialog.showModal();
        lock();
        trigger.setAttribute(EXPANDED_ATTRIBUTE, "true");
    };

    const close = (): void => dialog.close();

    // Escape and the backdrop close the dialog without passing through `close()`,
    // so the scroll release hangs off the dialog's own event instead.
    const onClose = (): void => {
        unlock();
        trigger.setAttribute(EXPANDED_ATTRIBUTE, "false");
    };

    // A click landing on the dialog itself is a click on the backdrop: the panel
    // fills the element, so anything inside has a descendant as its target.
    const onDialogClick = (event: Event): void => {
        if (event.target === dialog) {
            close();
        }
    };

    const onResize = (): void => {
        if (dialog.open && !isCompact(trigger)) {
            close();
        }
    };

    const closers = [...root.querySelectorAll<HTMLElement>(`[${Hook.Close}]`)];

    trigger.addEventListener("click", open);
    closers.forEach((element) => element.addEventListener("click", close));
    dialog.addEventListener("close", onClose);
    dialog.addEventListener("click", onDialogClick);
    window.addEventListener("resize", onResize);

    return () => {
        trigger.removeEventListener("click", open);
        closers.forEach((element) => element.removeEventListener("click", close));
        dialog.removeEventListener("close", onClose);
        dialog.removeEventListener("click", onDialogClick);
        window.removeEventListener("resize", onResize);
    };
}

// Both the dialog and its trigger sit inside regions a listing fragment
// replaces, so the listeners have to move to the new elements — unlike the
// selects next door, there is no per-element guard to make a second bind free.
let release = (): void => {};

const rebind = (): void => {
    release();
    release = bindFilterDrawer();
};

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", rebind);
} else {
    rebind();
}

events.observe(listingEvent(MutationPhase.After), rebind);
