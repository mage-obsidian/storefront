<script setup lang="ts">
import { ref, useId } from "vue";
import { Bars3Icon, XMarkIcon } from "@heroicons/vue/24/outline";
import Drawer from "MageObsidian_Storefront::elements/Drawer";
import Switcher from "MageObsidian_Storefront::navigation/Switcher";
import NavAccordion from "MageObsidian_Storefront::navigation/NavAccordion";
import WishlistCount from "MageObsidian_Storefront::wishlist/WishlistCount";
import CompareCount from "MageObsidian_Storefront::compare/CompareCount";

// Mobile navigation island. The hamburger lives inside the island (mounted eager
// so it is interactive above the fold); the Drawer carries the dialog a11y. Nav
// links and the (optional) store/currency switchers are all server-provided, so
// the markup is data-driven, not hard-coded here — and the switchers reuse the
// same Switcher component as the desktop header (inline variant for the drawer).
// Wishlist/compare are hidden from the bar on mobile (they overflow it), so they
// move here as utility links — reusing the same reactive count islands the desktop
// header uses, mapped from `kind`.
interface NavLink {
    label: string;
    url: string;
    children?: NavLink[];
}

interface UtilityLink {
    label: string;
    url: string;
    kind: "wishlist" | "compare";
}

interface SwitcherGroup {
    label: string;
    srLabel: string;
    items: Array<{ label: string; url: string; current?: boolean }>;
}

withDefaults(
    defineProps<{
        links?: NavLink[];
        utilities?: UtilityLink[];
        label?: string;
        // Distinct from `label`: the dialog is labelled "Menu", so the inner nav
        // uses its own label to avoid a screen reader announcing "Menu, Menu".
        navLabel?: string;
        // Wordmark + home link for the drawer header, so a child theme rebrands
        // by passing props from the twig instead of overriding this component.
        brand?: string;
        homeUrl?: string;
        stores?: SwitcherGroup | null;
        currencies?: SwitcherGroup | null;
    }>(),
    {
        links: () => [],
        utilities: () => [],
        label: "Menu",
        navLabel: "Browse",
        brand: "OBSIDIAN",
        homeUrl: "",
        stores: null,
        currencies: null,
    },
);

const open = ref(false);
const drawerId = `mobile-menu-${useId()}`;

const countComponents = { wishlist: WishlistCount, compare: CompareCount };

const hasSwitcher = (group: SwitcherGroup | null) =>
    group && Array.isArray(group.items) && group.items.length > 1;
</script>

<template>
    <div class="lg:hidden">
        <button
            type="button"
            class="-ml-1 inline-flex h-10 w-10 items-center justify-center text-ink-soft transition-colors hover:text-ink"
            :aria-label="label"
            aria-haspopup="dialog"
            :aria-controls="drawerId"
            :aria-expanded="open ? 'true' : 'false'"
            @click="open = true"
        >
            <Bars3Icon class="h-6 w-6" />
        </button>

        <Drawer :id="drawerId" :open="open" side="left" :label="label" @close="open = false">
            <div class="flex items-center justify-between border-b border-ash-200 px-5 py-4">
                <a v-if="homeUrl" :href="homeUrl" class="font-display text-xl tracking-[0.16em] text-ink">{{ brand }}</a>
                <span v-else class="font-display text-xl tracking-[0.16em] text-ink">{{ brand }}</span>
                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center text-ink-soft transition-colors hover:text-ink"
                    :aria-label="`Close ${label}`"
                    @click="open = false"
                >
                    <XMarkIcon class="h-5 w-5" />
                </button>
            </div>

            <nav class="px-2 py-4" :aria-label="navLabel">
                <NavAccordion :items="links" />
            </nav>

            <nav v-if="utilities.length" class="flex flex-col border-t border-ash-200 px-2 py-4" :aria-label="label">
                <a
                    v-for="util in utilities"
                    :key="util.kind"
                    :href="util.url"
                    class="flex items-center gap-3 rounded-edge px-3 py-3 font-mono text-[0.8rem] uppercase tracking-[0.16em] text-ink-soft transition-colors hover:bg-ash-100 hover:text-ink"
                >
                    <component :is="countComponents[util.kind]" :label="util.label" />
                    {{ util.label }}
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
