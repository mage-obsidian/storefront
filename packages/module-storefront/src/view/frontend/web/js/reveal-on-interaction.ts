const HOVER_REVEAL = '[data-show-overlay="hover"], [data-show-button="hover"]';
const OVERLAY = ".pagebuilder-overlay";
const BUTTON = ".pagebuilder-banner-button, .pagebuilder-slide-button, [data-element='button']";
const NO_HOVER = "(hover: none)";

const bound = new WeakSet<Element>();

export function supportsHover(view: Window): boolean {
    return view.matchMedia?.(NO_HOVER)?.matches !== true;
}

export function overlayColorOf(root: Element): string {
    const overlay = root.querySelector(OVERLAY);

    return overlay?.getAttribute("data-overlay-color") ?? "transparent";
}

export function revealablesOf(root: Element): { overlay: HTMLElement | null; button: HTMLElement | null } {
    return {
        overlay: root.getAttribute("data-show-overlay") === "hover" ? root.querySelector(OVERLAY) : null,
        button: root.getAttribute("data-show-button") === "hover" ? root.querySelector(BUTTON) : null,
    };
}

export function setRevealed(root: HTMLElement, revealed: boolean): void {
    const { overlay, button } = revealablesOf(root);

    root.setAttribute("data-revealed", String(revealed));
    if (overlay) {
        overlay.style.backgroundColor = revealed ? overlayColorOf(root) : "transparent";
    }
    if (button) {
        button.style.opacity = revealed ? "1" : "0";
    }
}

export function enhanceReveal(root: HTMLElement, view: Window = window): boolean {
    if (bound.has(root)) {
        return false;
    }

    const { overlay, button } = revealablesOf(root);
    if (overlay === null && button === null) {
        return false;
    }

    bound.add(root);

    if (!supportsHover(view)) {
        setRevealed(root, true);
        return true;
    }

    setRevealed(root, false);
    root.addEventListener("mouseenter", () => setRevealed(root, true));
    root.addEventListener("mouseleave", () => setRevealed(root, false));
    root.addEventListener("focusin", () => setRevealed(root, true));
    root.addEventListener("focusout", (event) => {
        const next = (event as FocusEvent).relatedTarget;
        if (next instanceof Node && root.contains(next)) {
            return;
        }
        setRevealed(root, false);
    });

    return true;
}

export function enhanceReveals(root: ParentNode = document, view: Window = window): void {
    root.querySelectorAll<HTMLElement>(HOVER_REVEAL).forEach((element) => enhanceReveal(element, view));
}

enhanceReveals();
