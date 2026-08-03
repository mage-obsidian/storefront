import events from "MageObsidian_ModernFrontend::js/events";
import { MutationPhase } from "mage-obsidian/runtime/mutationEvent.ts";
import { listingEvent } from "MageObsidian_Storefront::js/listing-events";

const SELECTOR = "select[data-nav-select]";
const BOUND = "navSelectBound";

export function bindNavSelect(select: HTMLSelectElement, navigate: (url: string) => void): void {
    if (select.dataset[BOUND]) {
        return;
    }
    select.dataset[BOUND] = "1";
    select.addEventListener("change", () => {
        if (select.value) {
            navigate(select.value);
        }
    });
}

export function bindNavSelects(
    root: ParentNode = document,
    navigate: (url: string) => void = (url) => window.location.assign(url),
): void {
    root.querySelectorAll<HTMLSelectElement>(SELECTOR).forEach((select) =>
        bindNavSelect(select, navigate),
    );
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => bindNavSelects());
} else {
    bindNavSelects();
}

// A listing swapped in from the server brings unbound selects with it. The
// per-element guard makes re-running this a no-op for everything already wired.
events.observe(listingEvent(MutationPhase.After), () => bindNavSelects());
