// Test stub for the engine's storefront event bus
// (`MageObsidian_ModernFrontend::js/events`), aliased in vitest.config.js.
// Mirrors the observe/dispatch contract of mage-obsidian/runtime/eventManager,
// which has its own suite; here it only has to let a test subscribe and read
// back what the cart flow announced.
type Observer = (data: Record<string, unknown>) => void | Promise<void>;

const observers: Record<string, Observer[]> = {};

export const dispatched: Array<{ event: string; data: Record<string, unknown> }> = [];

export const events = {
    observe(event: string, observer: Observer): () => void {
        (observers[event] ??= []).push(observer);
        return () => {
            const at = observers[event].indexOf(observer);
            if (at > -1) {
                observers[event].splice(at, 1);
            }
        };
    },

    async dispatch<T extends object>(event: string, data: T): Promise<T> {
        dispatched.push({ event, data: data as Record<string, unknown> });
        for (const observer of [...(observers[event] ?? [])]) {
            await observer(data as Record<string, unknown>);
        }
        return data;
    },
};

export function __reset(): void {
    for (const key of Object.keys(observers)) {
        delete observers[key];
    }
    dispatched.length = 0;
}

export default events;
