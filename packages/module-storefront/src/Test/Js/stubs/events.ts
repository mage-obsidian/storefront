type Observer = (data: Record<string, unknown>) => void | Promise<void>;

interface DispatchOptions {
    sticky?: boolean;
    mirror?: boolean;
}

interface DispatchHook {
    start?(event: string, data: object, options: DispatchOptions): void;
    end?(event: string, data: object, options: DispatchOptions): void;
}

const observers: Record<string, Observer[]> = {};
const hooks: DispatchHook[] = [];

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

    onDispatch(hook: DispatchHook): () => void {
        hooks.push(hook);
        return () => {
            const at = hooks.indexOf(hook);
            if (at > -1) {
                hooks.splice(at, 1);
            }
        };
    },

    async dispatch<T extends object>(
        event: string,
        data: T,
        options: DispatchOptions = {},
    ): Promise<T> {
        dispatched.push({ event, data: data as Record<string, unknown> });
        hooks.slice().forEach((hook) => hook.start?.(event, data, options));
        for (const observer of [...(observers[event] ?? [])]) {
            await observer(data as Record<string, unknown>);
        }
        hooks.slice().forEach((hook) => hook.end?.(event, data, options));
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
