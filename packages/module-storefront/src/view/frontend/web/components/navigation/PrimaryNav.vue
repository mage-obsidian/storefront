<script setup lang="ts">
import { ref, onBeforeUnmount, nextTick, useId } from "vue";
import Icon from "MageObsidian_ModernFrontend::elements/Icon";

interface NavLink {
    label: string;
    url: string;
    active?: boolean;
    children?: NavLink[];
}

withDefaults(
    defineProps<{
        links?: NavLink[];
        label?: string;
        moreLabel?: string;
    }>(),
    { links: () => [], label: "Primary", moreLabel: "More" },
);

const moreWrap = ref<HTMLElement | null>(null);
const trigger = ref<HTMLElement | null>(null);
const panel = ref<HTMLElement | null>(null);
const panelId = useId();
const open = ref(false);

const onDocumentClick = (event: Event): void => {
    if (moreWrap.value && !moreWrap.value.contains(event.target as Node | null)) {
        close(false);
    }
};

const openPanel = (): void => {
    open.value = true;
    document.addEventListener("click", onDocumentClick, true);
    nextTick(() => panel.value?.querySelector("a")?.focus());
};

const close = (returnFocus = true): void => {
    if (!open.value) {
        return;
    }
    open.value = false;
    document.removeEventListener("click", onDocumentClick, true);
    if (returnFocus) {
        trigger.value?.focus();
    }
};

const toggle = (): void => (open.value ? close(false) : openPanel());

const flyoutIndex = ref<number | null>(null);
const flyoutSuppressed = ref(false);

const openFlyout = (index: number): void => {
    if (!flyoutSuppressed.value) {
        flyoutIndex.value = index;
    }
};

const hoverFlyout = (index: number): void => {
    flyoutSuppressed.value = false;
    openFlyout(index);
};

const closeFlyout = (): void => {
    flyoutIndex.value = null;
};

const onFlyoutFocusOut = (event: FocusEvent): void => {
    const wrapper = event.currentTarget as HTMLElement;
    if (!wrapper.contains(event.relatedTarget as Node | null)) {
        closeFlyout();
        flyoutSuppressed.value = false;
    }
};

const onFlyoutEscape = (event: KeyboardEvent): void => {
    closeFlyout();
    flyoutSuppressed.value = true;
    (event.currentTarget as HTMLElement).querySelector("a")?.focus();
};

onBeforeUnmount(() => {
    document.removeEventListener("click", onDocumentClick, true);
});
</script>

<template>
    <nav class="flex min-w-0 items-center gap-6 xl:gap-8" :aria-label="label">
        <template v-for="(link, i) in links" :key="link.label">
            <a
                v-if="!link.children || !link.children.length"
                data-nav-item
                :data-nav-index="i"
                :href="link.url"
                :aria-current="link.active ? 'page' : null"
                class="whitespace-nowrap font-mono text-[0.72rem] uppercase tracking-[0.18em] text-ink-soft transition-colors hover:text-ink"
            >
                {{ link.label }}
            </a>

            <div
                v-else
                data-nav-item
                :data-nav-index="i"
                class="relative"
                @mouseenter="hoverFlyout(i)"
                @mouseleave="closeFlyout()"
                @focusin="openFlyout(i)"
                @focusout="onFlyoutFocusOut($event)"
                @keydown.escape="onFlyoutEscape($event)"
            >
                <a
                    :href="link.url"
                    :aria-current="link.active ? 'page' : null"
                    aria-haspopup="true"
                    :aria-expanded="flyoutIndex === i ? 'true' : 'false'"
                    class="inline-flex items-center gap-1 whitespace-nowrap font-mono text-[0.72rem] uppercase tracking-[0.18em] text-ink-soft transition-colors hover:text-ink"
                >
                    {{ link.label }}
                    <Icon name="chevron-down" set="outline"
                        class="h-3 w-3 transition-transform"
                        :class="flyoutIndex === i ? 'rotate-180' : ''"
                        aria-hidden="true"
                    />
                </a>

                <div v-show="flyoutIndex === i" class="absolute left-0 top-full z-40 pt-3">
                    <ul
                        :aria-label="link.label"
                        class="min-w-[12rem] rounded-edge border border-ash-200 bg-alabaster/95 py-1 shadow-xl backdrop-blur-md"
                    >
                        <li v-for="child in link.children" :key="child.label">
                            <a
                                :href="child.url"
                                :aria-current="child.active ? 'page' : null"
                                class="block whitespace-nowrap px-4 py-2 font-mono text-[0.72rem] uppercase tracking-[0.16em] text-ink-soft transition-colors hover:bg-ash-100 hover:text-ink"
                            >
                                {{ child.label }}
                            </a>
                            <ul v-if="child.children && child.children.length" class="pb-1">
                                <li v-for="grandchild in child.children" :key="grandchild.label">
                                    <a
                                        :href="grandchild.url"
                                        class="block whitespace-nowrap px-4 py-1.5 pl-7 font-mono text-[0.66rem] uppercase tracking-[0.14em] text-ash-500 transition-colors hover:bg-ash-100 hover:text-ink"
                                    >
                                        {{ grandchild.label }}
                                    </a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </template>

        <div ref="moreWrap" data-nav-more class="relative" @keydown.escape="close()">
            <button
                ref="trigger"
                type="button"
                class="inline-flex items-center gap-1 whitespace-nowrap font-mono text-[0.72rem] uppercase tracking-[0.18em] text-ink-soft transition-colors hover:text-ink"
                aria-haspopup="true"
                :aria-controls="panelId"
                :aria-expanded="open ? 'true' : 'false'"
                @click="toggle"
            >
                {{ moreLabel }}
                <Icon name="chevron-down" set="outline"
                    class="h-3.5 w-3.5 transition-transform"
                    :class="open ? 'rotate-180' : ''"
                    aria-hidden="true"
                />
            </button>

            <ul
                v-if="open"
                :id="panelId"
                ref="panel"
                :aria-label="moreLabel"
                class="absolute right-0 z-40 mt-3 min-w-[11rem] rounded-edge border border-ash-200 bg-alabaster/95 py-1 shadow-xl backdrop-blur-md"
            >
                <li v-for="(link, i) in links" :key="link.label" :data-nav-overflow-index="i">
                    <a
                        :href="link.url"
                        :aria-current="link.active ? 'page' : null"
                        class="block whitespace-nowrap px-4 py-2 font-mono text-[0.72rem] uppercase tracking-[0.18em] text-ink-soft transition-colors hover:bg-ash-100 hover:text-ink"
                        @click="close(false)"
                    >
                        {{ link.label }}
                    </a>
                    <ul v-if="link.children && link.children.length" class="pb-1">
                        <li v-for="child in link.children" :key="child.label">
                            <a
                                :href="child.url"
                                class="block whitespace-nowrap px-4 py-1.5 pl-7 font-mono text-[0.66rem] uppercase tracking-[0.16em] text-ash-500 transition-colors hover:bg-ash-100 hover:text-ink"
                                @click="close(false)"
                            >
                                {{ child.label }}
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
</template>
