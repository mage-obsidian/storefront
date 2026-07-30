<script setup lang="ts">
import { computed, ref, watch, onMounted, onBeforeUnmount, useId } from "vue";
import Drawer from "MageObsidian_Storefront::elements/Drawer";
import Icon from "MageObsidian_ModernFrontend::elements/Icon";
import { useCustomerData } from "MageObsidian_ModernFrontend::js/customer-data";
import { useCart } from "MageObsidian_Storefront::js/useCart";
import { useValueFlash } from "MageObsidian_Storefront::js/useValueFlash";
import { notify, NotificationTone } from "MageObsidian_Storefront::js/notifications";
import { readUxRuntimeConfig } from "mage-obsidian/runtime/uxConfig.ts";
import { createCartQueue } from "MageObsidian_Checkout::js/cart-queue";

// Off-canvas mini-cart. Presentation reuses the foundation's shared Drawer; the
// contents come from Magento's `cart` customer-data section (reactive, FPC-safe),
// and quantity/removal delegate to useCart's native sidebar endpoints. The trigger
// is the header's [data-minicart-trigger] link: with no JS it navigates to the
// cart page; here we intercept it to open the drawer instead.
interface CartItemOption {
    label: string;
    // Magento's cart section emits a plain string for configurable/custom options
    // but an array (one entry per selection) for bundle/downloadable, and bundle
    // values carry trusted price markup.
    value: string | string[];
}

interface CartItem {
    item_id: number | string;
    qty: number | string;
    product_name?: string;
    product_url?: string;
    product_price?: string;
    product_image?: { src?: string; alt?: string };
    options?: CartItemOption[];
}

interface CartSection {
    items?: CartItem[];
    summary_count?: number | string;
    subtotal?: string;
}

const CART_SECTION = "cart";

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
const ux = readUxRuntimeConfig();

const section = computed<CartSection>(() => (customerData.section(CART_SECTION) ?? {}) as CartSection);
const items = computed(() => section.value.items ?? []);
const count = computed(() => Number(section.value.summary_count ?? 0));
const subtotal = computed(() => section.value.subtotal ?? "");
const isEmpty = computed(() => items.value.length === 0);

const open = ref(false);
const pending = ref<Array<number | string>>([]);
const bumped = ref<number | string | null>(null);

const syncing = computed(() => pending.value.length > 0);
const subtotalFlashing = useValueFlash(() => subtotal.value);

const drawerId = `minicart-${useId()}`;

const isPending = (item: CartItem): boolean => pending.value.includes(item.item_id);

// Flatten the option value (array for bundle/downloadable) and drop any price
// markup to a plain, escaped string — same as the bag page does server-side.
function optionText(value: string | string[]): string {
    const joined = Array.isArray(value) ? value.join(", ") : String(value ?? "");
    return joined.replace(/<[^>]*>/g, "");
}

function unitsOf(item: CartItem): number {
    return ux.summaryCountsQty ? Math.max(1, Number(item.qty) || 1) : 1;
}

function project(nextItems: CartItem[], countDelta: number): void {
    customerData.patch(CART_SECTION, {
        items: nextItems,
        summary_count: Math.max(0, count.value + countDelta),
    });
}

function bump(itemId: number | string): void {
    bumped.value = itemId;
    setTimeout(() => {
        if (bumped.value === itemId) {
            bumped.value = null;
        }
    }, 250);
}

async function run(
    item: CartItem,
    optimistic: () => void,
    mutate: () => Promise<{ ok: boolean; message?: string }>,
): Promise<void> {
    if (isPending(item)) {
        return;
    }
    pending.value = [...pending.value, item.item_id];
    const rollback = customerData.snapshot([CART_SECTION]);
    if (ux.optimistic) {
        optimistic();
    }

    try {
        const { ok, message } = await mutate();
        if (!ok) {
            customerData.restore(rollback);
            // The rollback is the instant part; the reload lands after it so the
            // server has the last word — it may have clamped the quantity rather
            // than rejected it outright.
            void customerData.reload([CART_SECTION]);
            void notify(message ?? props.labels.updateFailed ?? "Could not update your bag", NotificationTone.Warning);
        }
    } finally {
        pending.value = pending.value.filter((id) => id !== item.item_id);
    }
}

// Quantity is coalesced, not gated: holding down (+) used to drop every click
// after the first while the request was in flight.
const qtyQueue = createCartQueue({
    updateQty: async (key, qty) => {
        // The queue keys by string; the section's own id type goes to the endpoint.
        const itemId = items.value.find((entry) => String(entry.item_id) === key)?.item_id ?? key;
        const { ok, message } = await cart.updateItemQty(itemId, qty, props.updateUrl);
        if (!ok) {
            void customerData.reload([CART_SECTION]);
            void notify(message ?? props.labels.updateFailed ?? "Could not update your bag", NotificationTone.Warning);
        }
    },
    settle: async () => {},
});

function setQty(item: CartItem, qty: number | string): void {
    const next = Math.max(1, Math.trunc(Number(qty) || 0));
    if (next === Number(item.qty)) {
        return;
    }
    const delta = ux.summaryCountsQty ? next - Number(item.qty) : 0;
    bump(item.item_id);
    if (ux.optimistic) {
        project(
            items.value.map((entry) =>
                entry.item_id === item.item_id ? { ...entry, qty: next } : entry,
            ),
            delta,
        );
    }
    qtyQueue.setQty(String(item.item_id), next);
}

function onQtyInput(item: CartItem, event: Event): void {
    setQty(item, (event.target as HTMLInputElement).value);
}

function remove(item: CartItem): void {
    void run(
        item,
        () =>
            project(
                items.value.filter((entry) => entry.item_id !== item.item_id),
                -unitsOf(item),
            ),
        () => cart.removeItem(item.item_id, props.removeUrl),
    );
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
                <Icon name="x-mark" set="outline" class="h-5 w-5" />
            </button>
        </header>

        <p class="sr-only" role="status" aria-live="polite">{{ count }} {{ labels.items }}</p>

        <Transition name="minicart-panel" mode="out-in">
            <div v-if="isEmpty" key="empty" class="flex flex-1 flex-col items-center justify-center gap-3 px-6 text-center">
                <p class="font-display text-lg text-ink">{{ labels.empty }}</p>
                <p class="max-w-[24ch] text-sm text-ink-soft">{{ labels.emptyHint }}</p>
            </div>

            <div v-else key="filled" class="flex min-h-0 flex-1 flex-col">
                <TransitionGroup tag="ul" name="minicart-item" class="minicart-list flex-1 divide-y divide-ash-200 overflow-y-auto">
                    <li
                        v-for="item in items"
                        :key="item.item_id"
                        class="minicart-item grid grid-cols-[4.5rem_1fr] gap-4 px-5 py-5"
                    >
                        <a :href="item.product_url" class="minicart-thumb">
                            <img
                                v-if="item.product_image && item.product_image.src"
                                :src="item.product_image.src"
                                :alt="item.product_image.alt || item.product_name"
                                class="h-full w-full object-contain"
                                loading="lazy"
                                width="72"
                                height="90"
                            />
                        </a>

                        <div class="flex min-w-0 flex-col gap-1.5">
                            <div class="flex items-start gap-3">
                                <a
                                    :href="item.product_url"
                                    class="line-clamp-2 min-w-0 flex-1 text-sm leading-snug font-medium text-ink transition-colors hover:text-accent"
                                >
                                    {{ item.product_name }}
                                </a>
                                <button
                                    type="button"
                                    class="minicart-remove shrink-0 disabled:opacity-40"
                                    :aria-label="`${labels.remove} — ${item.product_name}`"
                                    :disabled="isPending(item)"
                                    @click="remove(item)"
                                >
                                    <Icon name="trash" set="outline" class="h-4 w-4" />
                                </button>
                            </div>

                            <ul v-if="item.options && item.options.length" class="minicart-options">
                                <li v-for="option in item.options" :key="option.label">
                                    {{ option.label }}: <span class="text-ink-soft">{{ optionText(option.value) }}</span>
                                </li>
                            </ul>

                            <div class="mt-1.5 flex items-end justify-between gap-3">
                                <div
                                    class="minicart-qty field-stepper field-stepper--compact"
                                    :class="{ 'is-bumped': bumped === item.item_id }"
                                >
                                    <button
                                        type="button"
                                        class="field-stepper__button"
                                        :aria-label="labels.decrease"
                                        :disabled="Number(item.qty) <= 1"
                                        @click="setQty(item, Number(item.qty) - 1)"
                                    >
                                        <Icon name="minus" set="outline" class="h-3.5 w-3.5" />
                                    </button>
                                    <input
                                        type="number"
                                        min="1"
                                        inputmode="numeric"
                                        class="field-stepper__input"
                                        :value="item.qty"
                                        :aria-label="`${labels.quantity} — ${item.product_name}`"
                                        @change="onQtyInput(item, $event)"
                                    />
                                    <button
                                        type="button"
                                        class="field-stepper__button"
                                        :aria-label="labels.increase"
                                        @click="setQty(item, Number(item.qty) + 1)"
                                    >
                                        <Icon name="plus" set="outline" class="h-3.5 w-3.5" />
                                    </button>
                                </div>

                                <span
                                    class="minicart-value minicart-line-price font-mono text-sm text-ink"
                                    :class="{ 'is-syncing': isPending(item) }"
                                    v-html="item.product_price"
                                ></span>
                            </div>
                        </div>
                    </li>
                </TransitionGroup>

                <footer class="border-t border-ash-200 px-5 py-4">
                    <div class="flex items-center justify-between pb-4 font-mono text-sm uppercase tracking-[0.12em] text-ink">
                        <span>{{ labels.subtotal }}</span>
                        <span
                            class="minicart-value px-1"
                            :class="{ 'is-syncing': syncing, 'is-flashing': subtotalFlashing }"
                            v-html="subtotal"
                        ></span>
                    </div>
                    <div class="flex flex-col gap-2">
                        <a
                            :href="checkoutUrl"
                            class="btn btn--solid"
                        >
                            {{ labels.checkout }}
                        </a>
                        <a
                            :href="cartUrl"
                            class="btn btn--outline"
                        >
                            {{ labels.viewBag }}
                        </a>
                    </div>
                </footer>
            </div>
        </Transition>
    </Drawer>
</template>
