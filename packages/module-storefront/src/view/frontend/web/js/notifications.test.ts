import { describe, it, expect, beforeEach } from "vitest";
import { NotificationTone, notify, onNotification } from "./notifications.ts";
import { __reset as __resetEvents } from "MageObsidian_ModernFrontend::js/events";

function drainPending(): void {
    onNotification(() => {})();
}

beforeEach(() => {
    __resetEvents();
    drainPending();
});

describe("notification buffer", () => {
    it("holds a notification raised before any host mounted", async () => {
        await notify("Thank you for registering.", NotificationTone.Success);

        const seen: string[] = [];
        onNotification((event) => seen.push(event.message));

        expect(seen).toEqual(["Thank you for registering."]);
    });

    it("replays the backlog in the order it arrived", async () => {
        await notify("First");
        await notify("Second");

        const seen: string[] = [];
        onNotification((event) => seen.push(event.message));

        expect(seen).toEqual(["First", "Second"]);
    });

    it("delivers straight through once a host is listening", async () => {
        const seen: string[] = [];
        const release = onNotification((event) => seen.push(event.message));

        await notify("Added to cart");
        expect(seen).toEqual(["Added to cart"]);

        release();

        const late: string[] = [];
        onNotification((event) => late.push(event.message));
        expect(late).toEqual([]);
    });

    it("drops the oldest when the backlog outgrows its cap", async () => {
        for (const n of [1, 2, 3, 4, 5, 6, 7]) {
            await notify(`Message ${n}`);
        }

        const seen: string[] = [];
        onNotification((event) => seen.push(event.message));

        expect(seen).toEqual([
            "Message 3",
            "Message 4",
            "Message 5",
            "Message 6",
            "Message 7",
        ]);
    });

    it("carries the tone and options through the backlog", async () => {
        await notify("Please confirm.", NotificationTone.Notice, { html: true, durationMs: 9000 });

        const seen: { tone: string; html?: boolean; durationMs?: number }[] = [];
        onNotification((event) =>
            seen.push({ tone: event.tone, html: event.html, durationMs: event.durationMs }),
        );

        expect(seen).toEqual([
            { tone: NotificationTone.Notice, html: true, durationMs: 9000 },
        ]);
    });
});
