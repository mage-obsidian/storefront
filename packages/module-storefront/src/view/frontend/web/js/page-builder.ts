/**
 * The behaviour Page Builder content assumes, without the library it assumes it
 * from. A merchant's saved content carries the markup and the data attributes;
 * what a Luma theme supplies on top is jQuery UI tabs and slick. This supplies
 * the same two behaviours over the platform's own attributes, and nothing else:
 * everything Page Builder renders is readable with this script absent.
 */

import { enhanceSlider, goToSlide } from "MageObsidian_Storefront::js/slider";

const TABS = '[data-content-type="tabs"]';
const TAB_ITEM = '[data-content-type="tab-item"]';
const SLIDER = '[data-content-type="slider"]';
const SLIDE = '[data-content-type="slide"]';


const bound = new WeakSet<EventTarget>();

const panelsOf = (tabs: Element): HTMLElement[] =>
    Array.from(tabs.querySelectorAll<HTMLElement>(TAB_ITEM)).filter((panel) => panel.closest(TABS) === tabs);

const headersOf = (tabs: Element): HTMLAnchorElement[] =>
    Array.from(tabs.querySelectorAll<HTMLAnchorElement>(".tabs-navigation .tab-header > a.tab-title"));

export function selectTab(tabs: Element, index: number): void {
    const headers = headersOf(tabs);
    const panels = panelsOf(tabs);
    const chosen = Math.min(Math.max(index, 0), Math.max(panels.length - 1, 0));

    panels.forEach((panel, position) => {
        panel.hidden = position !== chosen;
    });
    headers.forEach((header, position) => {
        const selected = position === chosen;
        header.setAttribute("aria-selected", String(selected));
        header.setAttribute("tabindex", selected ? "0" : "-1");
        header.parentElement?.setAttribute("aria-selected", String(selected));
    });
}

export function enhanceTabs(root: ParentNode = document): void {
    root.querySelectorAll<HTMLElement>(TABS).forEach((tabs) => {
        const headers = headersOf(tabs);
        const panels = panelsOf(tabs);
        if (headers.length === 0 || panels.length === 0) {
            return;
        }

        headers.forEach((header, index) => {
            header.setAttribute("role", "tab");
            const panel = panels[index];
            if (panel) {
                panel.setAttribute("role", "tabpanel");
                if (panel.id) {
                    header.setAttribute("aria-controls", panel.id);
                }
            }
            header.addEventListener("click", (event) => {
                event.preventDefault();
                selectTab(tabs, index);
            });
        });

        selectTab(tabs, Number.parseInt(tabs.getAttribute("data-active-tab") ?? "0", 10) || 0);
    });
}

/**
 * Page Builder's slider is the shared primitive with the platform's own
 * attributes read off the markup: the slides are `[data-content-type="slide"]`
 * rather than the primitive's own marker, and the author's autoplay and dot
 * choices come from Page Builder's data attributes.
 */
export function enhanceSliders(root: ParentNode = document, view: Window = window): void {
    root.querySelectorAll<HTMLElement>(SLIDER).forEach((slider) => {
        enhanceSlider(
            slider,
            {
                dots: slider.getAttribute("data-show-dots") !== "false",
                arrows: slider.getAttribute("data-show-arrows") === "true",
                autoplay: slider.getAttribute("data-autoplay") === "true",
                interval: Number.parseInt(slider.getAttribute("data-autoplay-speed") ?? "", 10) || 5000,
            },
            view,
            SLIDE,
        );
    });
}

export function enhancePageBuilder(root: ParentNode & EventTarget = document, view: Window = window): void {
    if (bound.has(root)) {
        return;
    }
    bound.add(root);

    enhanceTabs(root);
    enhanceSliders(root, view);
}

enhancePageBuilder();

// Re-exported so a caller that drives a Page Builder slider does not have to
// know which primitive is underneath it.
export { goToSlide };
