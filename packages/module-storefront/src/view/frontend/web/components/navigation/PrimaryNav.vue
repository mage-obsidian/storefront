<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick, useId } from "vue";
import { ChevronDownIcon } from "@heroicons/vue/24/outline";
import { computeVisibleCount } from "MageObsidian_Storefront::js/overflowNav";

// Desktop primary navigation island (priority+). The category bar is measured
// and the items that do not fit collapse into a "More" disclosure, so a store
// with many top-level categories never overflows the page. The bar is
// server-fed the same Navigation ViewModel as the mobile drawer and the footer.
// Measurement lives here; the overflow math is the pure `computeVisibleCount`.
interface NavLink {
    label: string;
    url: string;
    active?: boolean;
    children?: NavLink[];
}

const props = withDefaults(
    defineProps<{
        links?: NavLink[];
        label?: string;
        moreLabel?: string;
    }>(),
    { links: () => [], label: "Primary", moreLabel: "More" },
);

const navEl = ref<HTMLElement | null>(null);
const moreWrap = ref<HTMLElement | null>(null);
const trigger = ref<HTMLElement | null>(null);
const panel = ref<HTMLElement | null>(null);
const panelId = useId();

// While measuring, every item and the More trigger render so their intrinsic
// widths can be read; steady state hides the overflow behind the disclosure.
const measuring = ref(true);
const visibleCount = ref(props.links.length);
const open = ref(false);

let widths: number[] = [];
let gap = 0;
let moreWidth = 0;
let observer: ResizeObserver | null = null;

const hasOverflow = computed(() => visibleCount.value < props.links.length);
const overflowLinks = computed(() => props.links.slice(visibleCount.value));

const readMetrics = (): void => {
    const el = navEl.value;
    if (!el) {
        return;
    }
    const items = Array.from(el.querySelectorAll<HTMLElement>("[data-nav-item]"));
    widths = items.map((item) => item.offsetWidth);
    const styles = getComputedStyle(el);
    gap = parseFloat(styles.columnGap || styles.gap || "0") || 0;
    moreWidth = moreWrap.value?.offsetWidth ?? 0;
};

const recompute = (): void => {
    const el = navEl.value;
    if (el) {
        visibleCount.value = computeVisibleCount(widths, gap, moreWidth, el.clientWidth);
    }
};

// Show everything for one tick to read widths, then collapse. `overflow-x-clip`
// on the bar keeps that transient full render from ever growing the page.
const measure = async (): Promise<void> => {
    measuring.value = true;
    await nextTick();
    readMetrics();
    measuring.value = false;
    recompute();
};

// Disclosure wiring, mirroring Switcher.vue: the items are plain links, so this
// is a disclosure (Tab moves through links) rather than an ARIA menu widget.
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

// Subcategory flyouts (only for items that carry `children`). Hover and keyboard
// focus open the panel; because the panel is a child of the wrapper, mouseleave
// fires only when the pointer leaves both, so no close timer is needed. A plain
// tap on the parent link (touch, no hover) still navigates to the category — the
// progressive fallback.
const flyoutIndex = ref<number | null>(null);
// After Escape we refocus the parent link, which re-fires focusin; this guard
// keeps that from reopening the panel until focus actually leaves the item.
const flyoutSuppressed = ref(false);

const openFlyout = (index: number): void => {
    if (!measuring.value && !flyoutSuppressed.value) {
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

onMounted(async () => {
    // Measure with the real (mono) font so widths are not read against a
    // fallback; `document.fonts` is absent in some test DOMs, hence optional.
    await document.fonts?.ready?.catch(() => {});
    await measure();
    if (typeof ResizeObserver !== "undefined" && navEl.value) {
        observer = new ResizeObserver(() => recompute());
        observer.observe(navEl.value);
    }
});

// Item widths only change when the links themselves change; a viewport resize
// just recomputes from the cached widths (no relayout read → no resize loop).
watch(
    () => props.links,
    () => {
        visibleCount.value = props.links.length;
        void measure();
    },
);

onBeforeUnmount(() => {
    observer?.disconnect();
    document.removeEventListener("click", onDocumentClick, true);
});
</script>

<template>
    <nav
        ref="navEl"
        class="flex min-w-0 items-center gap-6 overflow-y-visible xl:gap-8"
        :class="measuring ? 'overflow-x-clip' : 'overflow-x-visible'"
        :aria-label="label"
    >
        <template v-for="(link, i) in links" :key="link.label">
            <a
                v-if="!link.children || !link.children.length"
                v-show="measuring || i < visibleCount"
                data-nav-item
                :href="link.url"
                :aria-current="link.active ? 'page' : null"
                class="whitespace-nowrap font-mono text-[0.72rem] uppercase tracking-[0.18em] text-ink-soft transition-colors hover:text-ink"
            >
                {{ link.label }}
            </a>

            <div
                v-else
                v-show="measuring || i < visibleCount"
                data-nav-item
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
                    <ChevronDownIcon
                        class="h-3 w-3 transition-transform"
                        :class="flyoutIndex === i ? 'rotate-180' : ''"
                        aria-hidden="true"
                    />
                </a>

                <ul
                    v-show="flyoutIndex === i"
                    :aria-label="link.label"
                    class="absolute left-0 z-40 mt-3 min-w-[12rem] rounded-edge border border-ash-200 bg-alabaster/95 py-1 shadow-xl backdrop-blur-md"
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
        </template>

        <div
            v-show="measuring || hasOverflow"
            ref="moreWrap"
            class="relative"
            @keydown.escape="close()"
        >
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
                <ChevronDownIcon
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
                <li v-for="link in overflowLinks" :key="link.label">
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
