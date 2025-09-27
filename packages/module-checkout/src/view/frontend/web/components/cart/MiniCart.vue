<script setup lang="ts">
import { computed, ref, watch, onMounted, onBeforeUnmount, useId } from "vue";
import Drawer from "MageObsidian_Storefront::elements/Drawer";
import { useCustomerData } from "MageObsidian_ModernFrontend::js/customer-data";
import { useCart } from "MageObsidian_Storefront::js/useCart";

// Off-canvas mini-cart. Presentation reuses the foundation's shared Drawer; the
// contents come from Magento's `cart` customer-data section (reactive, FPC-safe),
// and quantity/removal delegate to useCart's native sidebar endpoints. The trigger
// is the header's [data-minicart-trigger] link: with no JS it navigates to the
// cart page; here we intercept it to open the drawer instead.
interface CartItem {
    item_id: number | string;
    qty: number | string;
    product_name?: string;
    product_url?: string;
    product_price?: string;
    product_image?: { src?: string; alt?: string };
    options?: Array<{ label: string; value: string }>;
}

interface CartSection {
    items?: CartItem[];
    summary_count?: number | string;
    subtotal?: string;
}

const props = withDefaults(
    defineProps<{
        cartUrl: string;
        checkoutUrl: string;
        updateUrl: string;
        removeUrl: string;
        labels?: Record<string, string>;
    }>(),
    { labels: () => ({}) },
);

const customerData = useCustomerData();
const cart = useCart();

const section = computed<CartSection>(() => (customerData.section("cart") ?? {}) as CartSection);
const items = computed(() => section.value.items ?? []);
const count = computed(() => Number(section.value.summary_count ?? 0));
const subtotal = computed(() => section.value.subtotal ?? "");
const isEmpty = computed(() => items.value.length === 0);

const open = ref(false);
// Item id currently mutating — disables its controls so a reload can't race a
// second click. Cleared once the customer-data reload settles.
const busyId = ref<number | string | null>(null);

const drawerId = `minicart-${useId()}`;

async function setQty(item: CartItem, qty: number | string): Promise<void> {
    const next = Math.max(1, Math.trunc(Number(qty) || 0));
    if (next === Number(item.qty) || busyId.value) {
        return;
    }
    busyId.value = item.item_id;
    try {
        await cart.updateItemQty(item.item_id, next, props.updateUrl);
    } finally {
        busyId.value = null;
    }
}

function onQtyInput(item: CartItem, event: Event): void {
    setQty(item, (event.target as HTMLInputElement).value);
}

async function remove(item: CartItem): Promise<void> {
    if (busyId.value) {
        return;
    }
    busyId.value = item.item_id;
    try {
        await cart.removeItem(item.item_id, props.removeUrl);
    } finally {
        busyId.value = null;
    }
}

// Bind every header trigger: wire dialog semantics and open the drawer instead of
// navigating. Kept on the document so the island can mount anywhere.
const triggers: Element[] = [];
const onTriggerClick = (event: Event): void => {
    event.preventDefault();
    open.value = true;
};

onMounted(() => {
    document.querySelectorAll("[data-minicart-trigger]").forEach((trigger) => {
        trigger.setAttribute("aria-haspopup", "dialog");
        trigger.setAttribute("aria-controls", drawerId);
        trigger.setAttribute("aria-expanded", "false");
        trigger.addEventListener("click", onTriggerClick);
        triggers.push(trigger);
    });
});

onBeforeUnmount(() => {
    triggers.forEach((trigger) => trigger.removeEventListener("click", onTriggerClick));
});

watch(open, (isOpen) => {
    triggers.forEach((trigger) => trigger.setAttribute("aria-expanded", isOpen ? "true" : "false"));
});
</script>

<template>
    <Drawer :id="drawerId" :open="open" side="right" :label="labels.title" @close="open = false">
        <header class="flex items-center justify-between border-b border-ash-200 px-5 py-4">
            <h2 class="font-display text-xl tracking-[0.12em] text-ink">
                {{ labels.title }}
                <span v-if="count > 0" class="ml-1 font-mono text-sm text-ink-soft">({{ count }})</span>
            </h2>
            <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center text-ink-soft transition-colors hover:text-ink"
                :aria-label="labels.close"
                @click="open = false"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </header>

        <p class="sr-only" role="status" aria-live="polite">{{ count }} {{ labels.items }}</p>

        <div v-if="isEmpty" class="flex flex-1 flex-col items-center justify-center gap-3 px-6 text-center">
            <p class="font-display text-lg text-ink">{{ labels.empty }}</p>
            <p class="max-w-[24ch] text-sm text-ink-soft">{{ labels.emptyHint }}</p>
        </div>

        <template v-else>
            <ul class="flex-1 divide-y divide-ash-200 overflow-y-auto px-5">
                <li v-for="item in items" :key="item.item_id" class="flex gap-3 py-4">
                    <a :href="item.product_url" class="block h-20 w-16 shrink-0 overflow-hidden rounded-edge bg-ash-100">
                        <img
                            v-if="item.product_image && item.product_image.src"
                            :src="item.product_image.src"
                            :alt="item.product_image.alt || item.product_name"
                            class="h-full w-full object-cover"
                            loading="lazy"
                            width="64"
                            height="80"
                        />
                    </a>

                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <a :href="item.product_url" class="truncate text-sm font-medium text-ink transition-colors hover:text-accent">
                            {{ item.product_name }}
                        </a>
                        <ul v-if="item.options && item.options.length" class="text-xs text-ink-soft">
                            <li v-for="option in item.options" :key="option.label">
                                <span class="text-ash-500">{{ option.label }}:</span> {{ option.value }}
                            </li>
                        </ul>

                        <div class="mt-auto flex items-center justify-between gap-2 pt-2">
                            <div class="inline-flex items-center rounded-edge border border-ash-300">
                                <button
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center text-ink-soft transition-colors hover:text-ink disabled:opacity-40"
                                    :aria-label="labels.decrease"
                                    :disabled="busyId === item.item_id || Number(item.qty) <= 1"
                                    @click="setQty(item, Number(item.qty) - 1)"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />
                                    </svg>
                                </button>
                                <input
                                    type="number"
                                    min="1"
                                    inputmode="numeric"
                                    class="h-8 w-10 border-x border-ash-300 bg-transparent text-center font-mono text-sm text-ink [appearance:textfield] focus:outline-none [&::-webkit-inner-spin-button]:appearance-none"
                                    :value="item.qty"
                                    :aria-label="`${labels.quantity} — ${item.product_name}`"
                                    :disabled="busyId === item.item_id"
                                    @change="onQtyInput(item, $event)"
                                />
                                <button
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center text-ink-soft transition-colors hover:text-ink disabled:opacity-40"
                                    :aria-label="labels.increase"
                                    :disabled="busyId === item.item_id"
                                    @click="setQty(item, Number(item.qty) + 1)"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                                    </svg>
                                </button>
                            </div>

                            <span class="font-mono text-sm text-ink" v-html="item.product_price"></span>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="self-start text-ash-400 transition-colors hover:text-accent disabled:opacity-40"
                        :aria-label="`${labels.remove} — ${item.product_name}`"
                        :disabled="busyId === item.item_id"
                        @click="remove(item)"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                    </button>
                </li>
            </ul>

            <footer class="border-t border-ash-200 px-5 py-4">
                <div class="flex items-center justify-between pb-4 font-mono text-sm uppercase tracking-[0.12em] text-ink">
                    <span>{{ labels.subtotal }}</span>
                    <span v-html="subtotal"></span>
                </div>
                <div class="flex flex-col gap-2">
                    <a
                        :href="checkoutUrl"
                        class="inline-flex items-center justify-center rounded-edge bg-ink px-4 py-3 font-mono text-xs uppercase tracking-[0.16em] text-alabaster transition-colors hover:bg-obsidian-800"
                    >
                        {{ labels.checkout }}
                    </a>
                    <a
                        :href="cartUrl"
                        class="inline-flex items-center justify-center rounded-edge border border-ash-300 px-4 py-3 font-mono text-xs uppercase tracking-[0.16em] text-ink-soft transition-colors hover:border-ink hover:text-ink"
                    >
                        {{ labels.viewBag }}
                    </a>
                </div>
            </footer>
        </template>
    </Drawer>
</template>
