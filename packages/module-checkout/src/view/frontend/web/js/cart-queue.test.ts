import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { createCartQueue, QTY_DEBOUNCE_MS } from "./cart-queue";

interface Recorder {
    sent: Array<[string, number]>;
    settles: number;
    busy: boolean[];
}

const build = (
    recorder: Recorder,
    updateQty?: (itemId: string, qty: number) => Promise<void>,
) =>
    createCartQueue({
        updateQty:
            updateQty ??
            (async (itemId, qty) => {
                recorder.sent.push([itemId, qty]);
            }),
        settle: async () => {
            recorder.settles += 1;
        },
        onBusyChange: (busy) => recorder.busy.push(busy),
    });

const recorder = (): Recorder => ({ sent: [], settles: 0, busy: [] });

beforeEach(() => vi.useFakeTimers());
afterEach(() => vi.useRealTimers());

const settle = async (): Promise<void> => {
    await vi.advanceTimersByTimeAsync(QTY_DEBOUNCE_MS);
    await vi.runAllTimersAsync();
};

describe("cart queue — quantity", () => {
    it("collapses a burst of clicks into one request carrying the last value", async () => {
        const log = recorder();
        const queue = build(log);

        for (const qty of [3, 4, 5, 6, 7, 8]) {
            queue.setQty("15", qty);
            await vi.advanceTimersByTimeAsync(80);
        }
        await settle();

        expect(log.sent).toEqual([["15", 8]]);
        expect(log.settles).toBe(1);
    });

    it("never drops a click that lands while a request is in flight", async () => {
        const log = recorder();
        let release: (() => void) | null = null;
        const queue = build(log, async (itemId, qty) => {
            log.sent.push([itemId, qty]);
            if (log.sent.length === 1) {
                await new Promise<void>((resolve) => {
                    release = resolve;
                });
            }
        });

        queue.setQty("15", 3);
        await settle();
        expect(log.sent).toEqual([["15", 3]]);

        queue.setQty("15", 9);
        await vi.advanceTimersByTimeAsync(QTY_DEBOUNCE_MS);
        release?.();
        await vi.runAllTimersAsync();

        expect(log.sent).toEqual([
            ["15", 3],
            ["15", 9],
        ]);
    });

    it("keeps one entry per line when several lines change", async () => {
        const log = recorder();
        const queue = build(log);

        queue.setQty("15", 2);
        queue.setQty("16", 4);
        queue.setQty("15", 7);
        await settle();

        expect(log.sent).toEqual([
            ["15", 7],
            ["16", 4],
        ]);
        expect(log.settles).toBe(1);
    });
});

describe("cart queue — other mutations", () => {
    it("serialises jobs and settles once when the chain drains", async () => {
        const log = recorder();
        const queue = build(log);
        const order: string[] = [];

        void queue.mutate(async () => {
            order.push("remove");
        });
        void queue.mutate(async () => {
            order.push("coupon");
        });
        await vi.runAllTimersAsync();

        expect(order).toEqual(["remove", "coupon"]);
        expect(log.settles).toBe(1);
    });

    it("commits a pending quantity before running the mutation", async () => {
        const log = recorder();
        const queue = build(log);
        const order: string[] = [];

        queue.setQty("15", 6);
        void queue.mutate(async () => {
            order.push("remove");
        });
        await vi.runAllTimersAsync();

        expect(log.sent).toEqual([["15", 6]]);
        expect(order).toEqual(["remove"]);
        expect(log.settles).toBe(1);
    });

    it("keeps running after a job throws", async () => {
        const log = recorder();
        const queue = build(log);
        const order: string[] = [];

        void queue.mutate(async () => {
            throw new Error("network");
        });
        void queue.mutate(async () => {
            order.push("second");
        });
        await vi.runAllTimersAsync();

        expect(order).toEqual(["second"]);
        expect(log.settles).toBe(1);
        expect(queue.idle()).toBe(true);
    });

    it("reports busy once at the start and once when everything has drained", async () => {
        const log = recorder();
        const queue = build(log);

        queue.setQty("15", 3);
        void queue.mutate(async () => {});
        await vi.runAllTimersAsync();

        expect(log.busy).toEqual([true, false]);
    });
});
