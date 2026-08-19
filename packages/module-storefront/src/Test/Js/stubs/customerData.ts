// Test stub for the engine's customer-data bridge
// (`MageObsidian_ModernFrontend::js/customer-data`), aliased in vitest.config.js.
// Holds a reactive section map so cart components react to `__setSection`, and
// records `reload()` calls so the add-to-cart flow can be asserted.
//
// `reload()` mirrors the real store's contract: it merges the server response
// and awaits a `section_reload_after` dispatch before resolving, so consumers
// that drain a section from that event can be exercised.
import { ref } from "vue";
import { LifecycleEvent } from "mage-obsidian/runtime/lifecycleEvents.ts";
import events from "./events";

type Section = Record<string, unknown>;

const sections = ref<Record<string, Section>>({});
let reloadResponse: Record<string, Section> = {};

export function __setSection(name: string, value: Section): void {
    sections.value = { ...sections.value, [name]: value };
}

export function __setReloadResponse(incoming: Record<string, Section>): void {
    reloadResponse = incoming;
}

interface ReloadFn {
    (...args: unknown[]): Promise<void>;
    calls: unknown[][];
}

export const reload: ReloadFn = Object.assign(
    async (...args: unknown[]): Promise<void> => {
        reload.calls.push(args);
        const names = Array.isArray(args[0]) ? (args[0] as string[]) : [];
        sections.value = { ...sections.value, ...reloadResponse };
        await events.dispatch(LifecycleEvent.SectionReloadAfter, {
            names,
            changed: Object.keys(reloadResponse),
        });
    },
    { calls: [] as unknown[][] },
);

export function __reset(): void {
    sections.value = {};
    reload.calls = [];
    reloadResponse = {};
}

export function useCustomerData() {
    return {
        section: (name: string): Section | null => sections.value[name] ?? null,
        reload,
        patch: (name: string, partial: Section): void => {
            sections.value = {
                ...sections.value,
                [name]: { ...(sections.value[name] ?? {}), ...partial },
            };
        },
        snapshot: (): Record<string, Section> => ({ ...sections.value }),
        restore: (previous: Record<string, Section>): void => {
            sections.value = { ...previous };
        },
    };
}

export default useCustomerData;
