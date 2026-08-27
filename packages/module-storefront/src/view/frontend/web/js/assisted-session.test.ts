import { beforeEach, describe, expect, it } from "vitest";
import { noticeFor, renderNotice } from "./assisted-session.ts";

const banner = (): HTMLElement => {
    document.body.innerHTML = `
        <div data-assisted-session="You are connected as %1 on %2" hidden>
            <p data-assisted-session-text></p>
            <a href="/customer/account/logout/">Close session</a>
        </div>`;
    return document.querySelector("[data-assisted-session]") as HTMLElement;
};

const text = (): string => document.querySelector("[data-assisted-session-text]")?.textContent ?? "";

beforeEach(() => {
    document.body.innerHTML = "";
});

describe("noticeFor", () => {
    it("names the customer and the store the session belongs to", () => {
        expect(
            noticeFor({ adminUserId: 3, websiteName: "Main Website" }, "Ada Obsidian", "You are connected as %1 on %2"),
        ).toBe("You are connected as Ada Obsidian on Main Website");
    });
});

describe("renderNotice", () => {
    /**
     * The point of the banner: a customer whose session an administrator is
     * inside has a right to know, and this is the only thing on the page that
     * says so.
     */
    it("shows who is being assisted once the sections arrive", () => {
        const element = banner();

        expect(renderNotice(element, { adminUserId: 3, websiteName: "Main Website" }, { fullname: "Ada" })).toBe(true);
        expect(element.hidden).toBe(false);
        expect(text()).toBe("You are connected as Ada on Main Website");
    });

    // The markup ships on every page, cacheable and empty; an ordinary shopper
    // must never see it.
    it("stays hidden for an ordinary shopper", () => {
        const element = banner();

        expect(renderNotice(element, {}, { fullname: "Ada" })).toBe(false);
        expect(element.hidden).toBe(true);
    });

    it("stays hidden before the sections have arrived", () => {
        const element = banner();

        expect(renderNotice(element, undefined, undefined)).toBe(false);
        expect(element.hidden).toBe(true);
    });

    // An id of zero is not an administrator, and neither is an empty string.
    it("does not take an empty administrator for a real one", () => {
        expect(renderNotice(banner(), { adminUserId: "", websiteName: "Main" }, { fullname: "Ada" })).toBe(false);
    });
});
