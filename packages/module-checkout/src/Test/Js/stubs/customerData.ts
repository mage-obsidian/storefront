// Test stub for the engine's customer-data bridge
// (`MageObsidian_ModernFrontend::js/customer-data`), aliased in vitest.config.js.
// Holds a reactive section map so the mini-cart reacts to `__setSection`, and
// records `reload()` calls so the qty/remove flow can be asserted.
import { ref } from "vue";

type Section = Record<string, unknown>;

const sections = ref<Record<string, Section>>({});

export function __setSection(name: string, value: Section): void {
    sections.value = { ...sections.value, [name]: value };
}

interface ReloadFn {
    (...args: unknown[]): Promise<void>;
    calls: unknown[][];
}

export const reload: ReloadFn = Object.assign(
    (...args: unknown[]): Promise<void> => {
        reload.calls.push(args);
        return Promise.resolve();
    },
    { calls: [] as unknown[][] },
);

export function __reset(): void {
    sections.value = {};
    reload.calls = [];
}

export function useCustomerData() {
    return {
        section: (name: string): Section | null => sections.value[name] ?? null,
        reload,
    };
}

export default useCustomerData;
