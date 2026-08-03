type Observer = (data: unknown) => unknown;

const observers: Record<string, Observer[]> = {};
const dispatched: Record<string, unknown[]> = {};

export const events = {
    observe(event: string, observer: Observer): () => void {
        const list = (observers[event] ??= []);
        list.push(observer);

        return () => {
            const at = list.indexOf(observer);
            if (at > -1) {
                list.splice(at, 1);
            }
        };
    },

    async dispatch(event: string, data: unknown): Promise<unknown> {
        (dispatched[event] ??= []).push(data);
        for (const observer of (observers[event] ?? []).slice()) {
            await observer(data);
        }

        return data;
    },

    recorded(event: string): unknown[] {
        return dispatched[event] ?? [];
    },

    reset(): void {
        Object.keys(observers).forEach((key) => delete observers[key]);
        Object.keys(dispatched).forEach((key) => delete dispatched[key]);
    },
};

export default events;
