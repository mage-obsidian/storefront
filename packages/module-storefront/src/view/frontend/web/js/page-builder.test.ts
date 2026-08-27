import { beforeEach, describe, expect, it, vi } from "vitest";
import { enhanceSliders, enhanceTabs, goToSlide, selectTab } from "./page-builder.ts";

const tabsMarkup = (activeTab = "0"): HTMLElement => {
    document.body.innerHTML = `
        <div data-content-type="tabs" data-appearance="default" data-active-tab="${activeTab}">
            <ul role="tablist" class="tabs-navigation">
                <li role="tab" class="tab-header"><a href="#one" class="tab-title"><span class="tab-title">One</span></a></li>
                <li role="tab" class="tab-header"><a href="#two" class="tab-title"><span class="tab-title">Two</span></a></li>
            </ul>
            <div class="tabs-content">
                <div data-content-type="tab-item" id="one"><p>First</p></div>
                <div data-content-type="tab-item" id="two"><p>Second</p></div>
            </div>
        </div>`;
    return document.querySelector('[data-content-type="tabs"]') as HTMLElement;
};

const sliderMarkup = (attributes = ""): HTMLElement => {
    document.body.innerHTML = `
        <div data-content-type="slider" data-appearance="default" class="pagebuilder-slider" ${attributes}>
            <div data-content-type="slide"><p>One</p></div>
            <div data-content-type="slide"><p>Two</p></div>
            <div data-content-type="slide"><p>Three</p></div>
        </div>`;
    const slider = document.querySelector('[data-content-type="slider"]') as HTMLElement;
    slider.scrollTo = vi.fn();
    return slider;
};

const panels = (): HTMLElement[] => Array.from(document.querySelectorAll('[data-content-type="tab-item"]'));

beforeEach(() => {
    document.body.innerHTML = "";
});

describe("enhanceTabs", () => {
    it("shows the tab the author marked active and hides the rest", () => {
        const tabs = tabsMarkup("1");

        enhanceTabs(tabs.ownerDocument);

        expect(panels().map((panel) => panel.hidden)).toEqual([true, false]);
    });

    it("switches panels when a header is clicked", () => {
        const tabs = tabsMarkup();
        enhanceTabs(tabs.ownerDocument);

        tabs.querySelectorAll<HTMLAnchorElement>("a.tab-title")[1].click();

        expect(panels().map((panel) => panel.hidden)).toEqual([true, false]);
    });

    // The header is an anchor to the panel's own id: following it would jump the
    // page and leave the tab set where it was.
    it("does not let the header navigate", () => {
        const tabs = tabsMarkup();
        enhanceTabs(tabs.ownerDocument);

        const header = tabs.querySelector("a.tab-title") as HTMLAnchorElement;
        const event = new MouseEvent("click", { bubbles: true, cancelable: true });
        header.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
    });

    it("tells assistive technology which tab is open", () => {
        const tabs = tabsMarkup();

        enhanceTabs(tabs.ownerDocument);

        const headers = tabs.querySelectorAll("a.tab-title");
        expect(headers[0].getAttribute("aria-selected")).toBe("true");
        expect(headers[1].getAttribute("aria-selected")).toBe("false");
        expect(headers[0].getAttribute("aria-controls")).toBe("one");
    });

    // An out-of-range index is what a deleted tab leaves behind.
    it("falls back to a tab that exists", () => {
        const tabs = tabsMarkup("9");

        enhanceTabs(tabs.ownerDocument);

        expect(panels().map((panel) => panel.hidden)).toEqual([true, false]);
    });

    it("leaves markup that is not a tab set alone", () => {
        document.body.innerHTML = '<div data-content-type="tabs"></div>';

        expect(() => enhanceTabs(document)).not.toThrow();
    });
});

describe("selectTab", () => {
    it("is idempotent", () => {
        const tabs = tabsMarkup();
        enhanceTabs(tabs.ownerDocument);

        selectTab(tabs, 1);
        selectTab(tabs, 1);

        expect(panels().map((panel) => panel.hidden)).toEqual([true, false]);
    });
});

describe("enhanceSliders", () => {
    it("adds one control per slide", () => {
        const slider = sliderMarkup();

        enhanceSliders(slider.ownerDocument);

        expect(document.querySelectorAll(".slider__dot")).toHaveLength(3);
        expect(document.querySelector('.slider__dot[aria-current="true"]')).not.toBeNull();
    });

    it("honours an author who turned the controls off", () => {
        const slider = sliderMarkup('data-show-dots="false"');

        enhanceSliders(slider.ownerDocument);

        expect(document.querySelectorAll(".slider__dot")).toHaveLength(0);
    });

    // One slide is not a slider; controls would be noise.
    it("leaves a single slide alone", () => {
        document.body.innerHTML = `
            <div data-content-type="slider" class="pagebuilder-slider">
                <div data-content-type="slide"><p>Only</p></div>
            </div>`;

        enhanceSliders(document);

        expect(document.querySelectorAll(".slider__dot")).toHaveLength(0);
    });

    it("does not build the controls twice", () => {
        const slider = sliderMarkup();

        enhanceSliders(slider.ownerDocument);
        enhanceSliders(slider.ownerDocument);

        expect(document.querySelectorAll(".slider__dot")).toHaveLength(3);
    });

    it("advances on its own when the author asked it to", () => {
        vi.useFakeTimers();
        const slider = sliderMarkup('data-autoplay="true" data-autoplay-speed="1000"');

        enhanceSliders(slider.ownerDocument, window);
        vi.advanceTimersByTime(1000);

        expect(slider.scrollTo).toHaveBeenCalled();
        vi.useRealTimers();
    });
});

describe("goToSlide", () => {
    it("wraps past the last slide", () => {
        const slider = sliderMarkup();
        enhanceSliders(slider.ownerDocument);

        goToSlide(slider, 3);

        const dots = document.querySelectorAll(".slider__dot");
        expect(dots[0].getAttribute("aria-current")).toBe("true");
    });
});
