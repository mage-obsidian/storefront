<script setup lang="ts">
import { ref } from "vue";
import { ChevronDownIcon } from "@heroicons/vue/24/outline";

// Recursive accordion for the mobile drawer: a nav tree where every branch is a
// category link (it still navigates) paired with a separate toggle button that
// expands its children. Recurses via its own filename, so it renders any depth
// the Navigation ViewModel emits. A flat item (no children) is a plain link.
interface NavLink {
    label: string;
    url: string;
    children?: NavLink[];
}

withDefaults(
    defineProps<{
        items?: NavLink[];
        level?: number;
    }>(),
    { items: () => [], level: 0 },
);

// Track expanded branches by label; a Set copy on each toggle keeps it reactive.
const openLabels = ref<Set<string>>(new Set());

const toggle = (label: string): void => {
    const next = new Set(openLabels.value);
    next.has(label) ? next.delete(label) : next.add(label);
    openLabels.value = next;
};
</script>

<template>
    <ul class="flex flex-col">
        <li v-for="item in items" :key="item.label">
            <div v-if="item.children && item.children.length" class="flex items-center">
                <a
                    :href="item.url"
                    class="flex-1 rounded-edge px-3 py-3 font-mono text-[0.8rem] uppercase tracking-[0.16em] text-ink-soft transition-colors hover:bg-ash-100 hover:text-ink"
                    :style="{ paddingLeft: `${0.75 + level * 0.75}rem` }"
                >
                    {{ item.label }}
                </a>
                <button
                    type="button"
                    class="inline-flex h-11 w-11 items-center justify-center text-ink-soft transition-colors hover:text-ink"
                    :aria-label="`Toggle ${item.label} submenu`"
                    :aria-expanded="openLabels.has(item.label) ? 'true' : 'false'"
                    @click="toggle(item.label)"
                >
                    <ChevronDownIcon
                        class="h-4 w-4 transition-transform"
                        :class="openLabels.has(item.label) ? 'rotate-180' : ''"
                        aria-hidden="true"
                    />
                </button>
            </div>
            <NavAccordion
                v-if="item.children && item.children.length"
                v-show="openLabels.has(item.label)"
                :items="item.children"
                :level="level + 1"
            />

            <a
                v-else
                :href="item.url"
                class="block rounded-edge px-3 py-3 font-mono text-[0.8rem] uppercase tracking-[0.16em] text-ink-soft transition-colors hover:bg-ash-100 hover:text-ink"
                :style="{ paddingLeft: `${0.75 + level * 0.75}rem` }"
            >
                {{ item.label }}
            </a>
        </li>
    </ul>
</template>
