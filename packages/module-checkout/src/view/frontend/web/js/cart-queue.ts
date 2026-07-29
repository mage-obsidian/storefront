export const QTY_DEBOUNCE_MS = 250;

type Job = () => Promise<void>;

export interface CartQueueOptions {
    updateQty: (itemId: string, qty: number) => Promise<void>;
    settle: () => Promise<void>;
    onBusyChange?: (busy: boolean) => void;
    delay?: number;
}

export interface CartQueue {
    setQty(itemId: string, qty: number): void;
    mutate(job: Job): Promise<void>;
    idle(): boolean;
}

/**
 * Serialises cart mutations and collapses quantity intent.
 *
 * The stepper used to write the new quantity straight to the input and then drop
 * the request whenever one was already in flight, so a burst of clicks showed a
 * number the server was never told about and the page snapped back to whatever
 * the single request had set. Here the latest quantity per line always wins, no
 * click is discarded, and a burst costs one request. Settling (re-fetching the
 * server-rendered region) happens once, after the last job of the chain.
 */
export function createCartQueue(options: CartQueueOptions): CartQueue {
    const desired = new Map<string, number>();
    let chain: Promise<void> = Promise.resolve();
    let depth = 0;
    let timer: ReturnType<typeof setTimeout> | null = null;

    const enqueue = (job: Job): Promise<void> => {
        depth += 1;
        if (depth === 1) {
            options.onBusyChange?.(true);
        }
        const next = chain
            .then(job)
            .catch(() => {})
            .then(async () => {
                depth -= 1;
                if (depth > 0) {
                    return;
                }
                try {
                    await options.settle();
                } catch {
                    /* settling is best-effort; the queue must not wedge */
                } finally {
                    options.onBusyChange?.(false);
                }
            });
        chain = next;

        return next;
    };

    const flushQty = async (): Promise<void> => {
        while (desired.size > 0) {
            const batch = [...desired.entries()];
            desired.clear();
            for (const [itemId, qty] of batch) {
                await options.updateQty(itemId, qty);
            }
        }
    };

    const commit = (): Promise<void> => {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }

        return enqueue(flushQty);
    };

    return {
        setQty(itemId: string, qty: number): void {
            desired.set(itemId, qty);
            if (timer !== null) {
                clearTimeout(timer);
            }
            timer = setTimeout(() => {
                timer = null;
                void enqueue(flushQty);
            }, options.delay ?? QTY_DEBOUNCE_MS);
        },
        mutate(job: Job): Promise<void> {
            if (desired.size > 0) {
                void commit();
            }

            return enqueue(job);
        },
        idle(): boolean {
            return depth === 0 && desired.size === 0 && timer === null;
        },
    };
}
