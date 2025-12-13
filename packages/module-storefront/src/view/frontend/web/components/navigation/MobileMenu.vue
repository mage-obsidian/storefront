<script setup lang="ts">
import { ref, useId } from "vue";
import Drawer from "MageObsidian_Storefront::elements/Drawer";
import Switcher from "MageObsidian_Storefront::navigation/Switcher";

// Mobile navigation island. The hamburger lives inside the island (mounted eager
// so it is interactive above the fold); the Drawer carries the dialog a11y. Nav
// links and the (optional) store/currency switchers are all server-provided, so
// the markup is data-driven, not hard-coded here — and the switchers reuse the
// same Switcher component as the desktop header (inline variant for the drawer).
interface NavLink {
    label: string;
    url: string;
}

interface SwitcherGroup {
    label: string;
    srLabel: string;
    items: Array<{ label: string; url: string; current?: boolean }>;
}

withDefaults(
    defineProps<{
        links?: NavLink[];
        label?: string;
        // Distinct from `label`: the dialog is labelled "Menu", so the inner nav
        // uses its own label to avoid a screen reader announcing "Menu, Menu".
        navLabel?: string;
        stores?: SwitcherGroup | null;
        currencies?: SwitcherGroup | null;
    }>(),
    {
        links: () => [],
        label: "Menu",
        navLabel: "Browse",
        stores: null,
        currencies: null,
    },
);

const open = ref(false);
const drawerId = `mobile-menu-${useId()}`;

const hasSwitcher = (group: SwitcherGroup | null) =>
    group && Array.isArray(group.items) && group.items.length > 1;
</script>

<template>
    <div class="md:hidden">
        <button
            type="button"
            class="-ml-1 inline-flex h-10 w-10 items-center justify-center text-ink-soft transition-colors hover:text-ink"
            :aria-label="label"
            aria-haspopup="dialog"
            :aria-controls="drawerId"
            :aria-expanded="open ? 'true' : 'false'"
            @click="open = true"
        >
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
            </svg>
        </button>

        <Drawer :id="drawerId" :open="open" side="left" :label="label" @close="open = false">
            <div class="flex items-center justify-between border-b border-ash-200 px-5 py-4">
                <span class="font-display text-xl tracking-[0.16em] text-ink">OBSIDIAN</span>
                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center text-ink-soft transition-colors hover:text-ink"
                    :aria-label="`Close ${label}`"
                    @click="open = false"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <nav class="flex flex-col px-2 py-4" :aria-label="navLabel">
                <a
                    v-for="link in links"
                    :key="link.label"
                    :href="link.url"
                    class="rounded-edge px-3 py-3 font-mono text-[0.8rem] uppercase tracking-[0.16em] text-ink-soft transition-colors hover:bg-ash-100 hover:text-ink"
                >
                    {{ link.label }}
                </a>
            </nav>

            <div v-if="hasSwitcher(stores) || hasSwitcher(currencies)" class="mt-auto flex flex-col gap-4 border-t border-ash-200 px-2 py-5">
                <Switcher
                    v-if="hasSwitcher(stores)"
                    variant="inline"
                    :label="stores.label"
                    :sr-label="stores.srLabel"
                    :items="stores.items"
                />
                <Switcher
                    v-if="hasSwitcher(currencies)"
                    variant="inline"
                    :label="currencies.label"
                    :sr-label="currencies.srLabel"
                    :items="currencies.items"
                />
            </div>
        </Drawer>
    </div>
</template>
