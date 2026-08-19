import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import {
    MESSAGES_COOKIE,
    announce,
    consumeLastBatch,
    firstErrorText,
    normalize,
    observeSectionMessages,
    prepare,
    readCookieMessages,
    sanitize,
    toneFor,
    withSuppressedNotifications,
} from "./session-messages.ts";
import { NotificationTone, onNotification } from "./notifications.ts";
import events, { __reset as __resetEvents } from "MageObsidian_ModernFrontend::js/events";
import { readCookie } from "mage-obsidian/runtime/sectionStoreCore.ts";
import {
    __setSection,
    __reset,
    useCustomerData,
} from "MageObsidian_ModernFrontend::js/customer-data";

let shown: { message: string; tone: string; html?: boolean }[];
let releaseSink: (() => void) | null = null;

function setCookie(value: unknown): void {
    document.cookie = `${MESSAGES_COOKIE}=${encodeURIComponent(JSON.stringify(value))}; path=/`;
}

function setRawCookie(raw: string): void {
    document.cookie = `${MESSAGES_COOKIE}=${encodeURIComponent(raw)}; path=/`;
}

beforeEach(() => {
    __reset();
    __resetEvents();
    sessionStorage.clear();
    document.cookie = `${MESSAGES_COOKIE}=; max-age=0; path=/`;
    consumeLastBatch();
    shown = [];
    releaseSink = onNotification((event) => {
        shown.push({ message: event.message, tone: event.tone, html: event.html });
    });
});

afterEach(() => {
    releaseSink?.();
    releaseSink = null;
    vi.unstubAllGlobals();
});

describe("cookie channel", () => {
    it("parses the URL-decoded mage-messages cookie", () => {
        setCookie([{ type: "success", text: "Thank you for registering." }]);

        expect(readCookieMessages()).toEqual([
            { type: "success", text: "Thank you for registering." },
        ]);
    });

    it("expires the cookie as it reads it, so the message shows once", () => {
        setCookie([{ type: "success", text: "Saved." }]);

        readCookieMessages();

        expect(readCookie(document.cookie, MESSAGES_COOKIE)).toBe("");
    });

    it("returns nothing when the cookie is absent", () => {
        expect(readCookieMessages()).toEqual([]);
    });

    it("survives a corrupt payload and still clears the cookie", () => {
        setRawCookie("{not json");

        expect(() => readCookieMessages()).not.toThrow();
        expect(readCookieMessages()).toEqual([]);
        expect(readCookie(document.cookie, MESSAGES_COOKIE)).toBe("");
    });

    it("returns nothing when the payload is valid JSON but not an array", () => {
        setCookie({ type: "success", text: "Saved." });

        expect(readCookieMessages()).toEqual([]);
    });

    it("does not repeat a batch a failed cookie deletion left behind", () => {
        setCookie([{ type: "success", text: "Saved." }]);
        expect(readCookieMessages()).toHaveLength(1);

        setCookie([{ type: "success", text: "Saved." }]);
        expect(readCookieMessages()).toEqual([]);
    });

    it("caps a backlog the cookie accumulated, keeping the most recent", () => {
        setCookie([
            { type: "notice", text: "One." },
            { type: "notice", text: "Two." },
            { type: "notice", text: "Three." },
            { type: "notice", text: "Four." },
            { type: "notice", text: "Five." },
        ]);

        expect(readCookieMessages().map((m) => m.text)).toEqual(["Three.", "Four.", "Five."]);
    });
});

describe("section channel", () => {
    it("drains and announces when the reload actually returned messages", async () => {
        observeSectionMessages();
        __setSection("messages", { messages: [{ type: "success", text: "Added." }] });

        await events.dispatch("section_reload_after", {
            names: ["cart", "messages"],
            changed: ["cart", "messages"],
        });

        expect(shown.map((s) => s.message)).toEqual(["Added."]);
        expect(useCustomerData().section("messages")).toEqual({ messages: [] });
    });

    it("stays quiet when the reload did not carry the messages section", async () => {
        observeSectionMessages();
        __setSection("messages", { messages: [{ type: "error", text: "Stale." }] });

        await events.dispatch("section_reload_after", { names: ["cart"], changed: ["cart"] });

        expect(shown).toEqual([]);
    });
});

describe("tones", () => {
    it("maps every Magento message type", () => {
        expect(toneFor("success")).toBe(NotificationTone.Success);
        expect(toneFor("error")).toBe(NotificationTone.Error);
        expect(toneFor("warning")).toBe(NotificationTone.Warning);
        expect(toneFor("notice")).toBe(NotificationTone.Notice);
    });

    it("falls back to notice for an unknown or missing type", () => {
        expect(toneFor("whatever")).toBe(NotificationTone.Notice);
        expect(toneFor(undefined)).toBe(NotificationTone.Notice);
    });
});

describe("sanitising", () => {
    it("drops a script tag entirely", () => {
        expect(sanitize("Hi<script>alert(1)</script>").html).toBe("Hi");
    });

    it("strips event handlers and unknown attributes from an allowed tag", () => {
        const { html } = sanitize('<a href="/account/" onclick="steal()" target="_blank">Here</a>');

        expect(html).toContain('href="/account/"');
        expect(html).not.toContain("onclick");
        expect(html).not.toContain("target");
    });

    it("removes a javascript: href but keeps the text", () => {
        const { html } = sanitize('<a href="javascript:alert(1)">Click</a>');

        expect(html).not.toContain("javascript:");
        expect(html).toContain("Click");
    });

    it("unwraps a disallowed tag instead of losing its text", () => {
        expect(sanitize("<div>Kept</div>").html).toBe("Kept");
    });

    it("keeps a real link, so the confirmation message stays actionable", () => {
        const message = 'Please <a href="https://shop.test/confirm">confirm</a> your account.';

        expect(normalize(message)).toEqual({
            message,
            text: "Please confirm your account.",
            html: true,
        });
    });

    it("decodes an escaped message and marks it as plain text", () => {
        expect(normalize("The file &#039;art.txt&#039; has an invalid extension.")).toEqual({
            message: "The file 'art.txt' has an invalid extension.",
            text: "The file 'art.txt' has an invalid extension.",
            html: false,
        });
    });
});

describe("batching", () => {
    it("drops repeats and blank messages", () => {
        const batch = prepare([
            { type: "success", text: "Saved." },
            { type: "success", text: "Saved." },
            { type: "notice", text: "   " },
            { type: "error", text: "Nope." },
        ]);

        expect(batch.map((item) => item.text)).toEqual(["Saved.", "Nope."]);
    });

    it("announces each message on its own tone", async () => {
        await announce([
            { type: "success", text: "Saved." },
            { type: "error", text: "Nope." },
        ]);

        expect(shown).toEqual([
            { message: "Saved.", tone: NotificationTone.Success, html: false },
            { message: "Nope.", tone: NotificationTone.Error, html: false },
        ]);
    });

    it("hands the batch to the caller instead of the toast while suppressed", async () => {
        await withSuppressedNotifications(async () => {
            await announce([{ type: "error", text: "Invalid extension." }]);
        });

        expect(shown).toEqual([]);
        expect(firstErrorText(consumeLastBatch())).toBe("Invalid extension.");
    });

    it("hands a suppressed batch out only once", async () => {
        await withSuppressedNotifications(async () => {
            await announce([{ type: "error", text: "Invalid extension." }]);
        });

        expect(consumeLastBatch()).toHaveLength(1);
        expect(consumeLastBatch()).toEqual([]);
    });

    it("gives the caller plain text even when the server sent markup", async () => {
        await withSuppressedNotifications(async () => {
            await announce([
                { type: "error", text: '<a href="/help">Read this</a> and retry.' },
            ]);
        });

        expect(firstErrorText(consumeLastBatch())).toBe("Read this and retry.");
    });
});
