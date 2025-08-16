<script setup>
import { ref, onBeforeUnmount, nextTick, useId } from "vue";

// Reusable store / language / currency switcher. One component, two looks:
//   - dropdown (default): a DISCLOSURE — a button that toggles a list of links.
//     The items are navigation links, so this is intentionally not an ARIA menu
//     widget: Tab moves through the links naturally, and the only extra wiring is
//     aria-expanded/aria-controls plus focus handling (focus the first link on
//     open, return focus to the trigger on Escape/close).
//   - inline: a flat list under a label, for inside the mobile drawer where a
//     popover would overflow.
// Options are plain links to native Magento switch URLs (GET), so switching
// works even before the island hydrates.
const props = defineProps({
    label: { type: String, default: "" },
    srLabel: { type: String, default: "" },
    items: { type: Array, default: () => [] },
    variant: { type: String, default: "dropdown" },
});

const open = ref(false);
const root = ref(null);
const trigger = ref(null);
const panel = ref(null);
const panelId = useId();

const onDocumentClick = (event) => {
    if (root.value && !root.value.contains(event.target)) {
        close(false);
    }
};

const openPanel = () => {
    open.value = true;
    document.addEventListener("click", onDocumentClick, true);
    nextTick(() => panel.value?.querySelector("a")?.focus());
};

// returnFocus: send focus back to the trigger (keyboard close); skip it when the
// panel closes because the user clicked away or followed a link.
const close = (returnFocus = true) => {
    if (!open.value) {
        return;
    }
    open.value = false;
    document.removeEventListener("click", onDocumentClick, true);
    if (returnFocus) {
        trigger.value?.focus();
    }
};

const toggle = () => (open.value ? close(false) : openPanel());

onBeforeUnmount(() => document.removeEventListener("click", onDocumentClick, true));
</script>

<template>
    <div v-if="variant === 'inline'" class="flex flex-col gap-1">
        <span class="px-3 font-mono text-[0.62rem] uppercase tracking-[0.18em] text-ash-500">{{ srLabel }}</span>
        <div class="flex flex-wrap gap-x-4 gap-y-1 px-3">
            <a
                v-for="item in items"
                :key="item.label"
                :href="item.url"
                :aria-current="item.current ? 'true' : null"
                class="font-mono text-[0.78rem] uppercase tracking-[0.14em] transition-colors"
                :class="item.current ? 'text-ink' : 'text-ink-soft hover:text-ink'"
            >
                {{ item.label }}
            </a>
        </div>
    </div>

    <div v-else ref="root" class="relative" @keydown.escape="close()">
        <button
            ref="trigger"
            type="button"
            class="inline-flex items-center gap-1 font-mono text-[0.72rem] uppercase tracking-[0.12em] text-ink-soft transition-colors hover:text-ink"
            aria-haspopup="true"
            :aria-controls="panelId"
            :aria-label="label ? `${label} — ${srLabel}` : srLabel"
            :aria-expanded="open ? 'true' : 'false'"
            @click="toggle"
        >
            {{ label }}
            <svg class="h-3 w-3 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        <ul
            v-if="open"
            :id="panelId"
            ref="panel"
            :aria-label="srLabel"
            class="absolute right-0 z-40 mt-2 min-w-[7rem] rounded-edge border border-ash-200 bg-alabaster/95 py-1 shadow-xl backdrop-blur-md"
        >
            <li v-for="item in items" :key="item.label">
                <a
                    :href="item.url"
                    :aria-current="item.current ? 'true' : null"
                    class="block px-4 py-2 font-mono text-[0.72rem] uppercase tracking-[0.12em] transition-colors"
                    :class="item.current ? 'text-ink' : 'text-ink-soft hover:bg-ash-100 hover:text-ink'"
                    @click="close(false)"
                >
                    {{ item.label }}
                </a>
            </li>
        </ul>
    </div>
</template>
