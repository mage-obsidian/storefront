<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, onUnmounted, ref, watchEffect } from "vue";
import { useCheckout, CheckoutStep } from "MageObsidian_Checkout::js/useCheckout";
import { useCustomerData } from "MageObsidian_ModernFrontend::js/customer-data";
import StepRail from "MageObsidian_Checkout::checkout/StepRail";
import type { RegionData } from "MageObsidian_Storefront::js/address";

const PRIVATE_SECTION = "obsidian-checkout";
const BYPASS_PARAM = "obsidian_shell";
const PRIVATE_TIMEOUT_MS = 8000;

// Root of the Vue one-page checkout, replacing Magento's Knockout flow. It mounts
// eager (it IS the page) and is seeded from the server-primed CheckoutConfig +
// DirectoryData, so the step rail and order summary paint with zero REST
// round-trips. The per-step UIs are code-split (dynamic import) so a shopper only
// downloads the step they reach.
interface DirectoryData {
    countries: Array<{ value: string; label: string }>;
    regions: Record<string, RegionData[]>;
    statesRequired: string[];
    displayAllRegions: boolean;
    defaultCountry: string;
}

const props = withDefaults(
    defineProps<{
        config?: Record<string, unknown>;
        directory?: DirectoryData;
        loginUrl?: string;
        labels?: Record<string, string>;
        identificationLabels?: Record<string, string>;
        shippingLabels?: Record<string, string>;
        paymentLabels?: Record<string, string>;
        reviewLabels?: Record<string, string>;
        addressLabels?: Record<string, string>;
    }>(),
    {
        config: () => ({}),
        directory: () => ({ countries: [], regions: {}, statesRequired: [], displayAllRegions: false, defaultCountry: "" }),
        loginUrl: "",
        labels: () => ({}),
        identificationLabels: () => ({}),
        shippingLabels: () => ({}),
        paymentLabels: () => ({}),
        reviewLabels: () => ({}),
        addressLabels: () => ({}),
    },
);

const IdentificationStep = defineAsyncComponent(
    () => import("MageObsidian_Checkout::checkout/IdentificationStep"),
);
const ShippingStep = defineAsyncComponent(() => import("MageObsidian_Checkout::checkout/ShippingStep"));
const PaymentStep = defineAsyncComponent(() => import("MageObsidian_Checkout::checkout/PaymentStep"));
const ReviewStep = defineAsyncComponent(() => import("MageObsidian_Checkout::checkout/ReviewStep"));
const OnePageCheckout = defineAsyncComponent(() => import("MageObsidian_Checkout::checkout/OnePageCheckout"));

const checkout = useCheckout();
const customerData = useCustomerData();

// Cached, the props carry only the store-scoped half and the quote arrives through
// customer-data; uncached, the whole config is inlined. Seed from whichever has it.
checkout.initPublic({
    ...props.config,
    defaultCountry: props.directory.defaultCountry,
    statesRequired: props.directory.statesRequired,
});
const quoteWasInlined = Boolean(props.config?.quote);
if (quoteWasInlined) {
    checkout.applyPrivate(props.config);
}

// The version cookie only moves on POST, and a currency switch is a GET.
let revalidated = false;

function belongsToThisPage(section): boolean {
    const stamp = section?.context;
    if (!stamp || !props.config?.currencyCode) {
        return true;
    }

    return stamp.storeCode === props.config.storeCode && stamp.currencyCode === props.config.currencyCode;
}

const privateIsFresh = ref(quoteWasInlined);
if (!quoteWasInlined) {
    void customerData.reload([PRIVATE_SECTION]).then(() => {
        privateIsFresh.value = true;
    });
}

watchEffect(() => {
    const section = customerData.section(PRIVATE_SECTION);
    if (!section || !privateIsFresh.value) {
        return;
    }
    // A cart mutation reloads `cart` alone, leaving this section holding the
    // pre-mutation quote. Acting on it sends a shopper who just added an item
    // back to the bag page. The store's hydrate is already refetching.
    if (customerData.isStale()) {
        return;
    }
    if (!belongsToThisPage(section) && !revalidated) {
        revalidated = true;
        void customerData.reload([PRIVATE_SECTION]);
        return;
    }
    checkout.applyPrivate(section);
});

// A shared shell cannot run Magento's server-side empty-cart redirect, so restore
// its destination here once the quote is known.
let leaving = false;
watchEffect(() => {
    if (leaving || !checkout.ready || checkout.itemCount > 0) {
        return;
    }
    leaving = true;
    window.location.assign(`${props.config?.baseUrl ?? ""}checkout/cart/`);
});

const isOnePage = computed(() => checkout.layout === "onepage");

let fallbackTimer: ReturnType<typeof setTimeout> | null = null;

/** All-or-nothing: never leave a checkout that cannot know the cart. */
function fallbackToUncachedPage(): void {
    if (checkout.ready) {
        return;
    }
    const url = new URL(window.location.href);
    url.searchParams.set(BYPASS_PARAM, "0");
    window.location.assign(url.toString());
}

onMounted(() => {
    // On the cached path the batch already refetches `cart`; a second request
    // serialises behind it on the PHP session lock.
    if (quoteWasInlined) {
        customerData.reload(["cart"]);
    }

    if (checkout.ready || window.location.search.includes(`${BYPASS_PARAM}=0`)) {
        return;
    }
    fallbackTimer = setTimeout(fallbackToUncachedPage, PRIVATE_TIMEOUT_MS);
});

onUnmounted(() => {
    if (fallbackTimer !== null) {
        clearTimeout(fallbackTimer);
    }
});

const t = (key: string, fallback: string): string => props.labels?.[key] ?? fallback;

const steps = computed(() => {
    const raw = [
        { key: CheckoutStep.Identification, label: t("stepIdentification", "Identification") },
        { key: CheckoutStep.Shipping, label: t("stepShipping", "Shipping") },
        { key: CheckoutStep.Payment, label: t("stepPayment", "Payment") },
        { key: CheckoutStep.Review, label: t("stepReview", "Review") },
    ];
    const current = checkout.stepIndex;
    const furthest = checkout.furthestStepIndex;

    return raw.map((step, index) => {
        const blocked = step.key === CheckoutStep.Identification && checkout.isLoggedIn;
        return {
            ...step,
            index,
            done: index <= furthest && index !== current,
            active: index === current,
            reachable: index <= furthest && index !== current && !blocked,
        };
    });
});

const currentStepLabel = computed(
    () => steps.value.find((s) => s.key === checkout.step)?.label ?? "",
);
const isEmpty = computed(() => checkout.ready && checkout.itemCount === 0);
const grandTotalLabel = computed(() => {
    const segment = checkout.totalSegments.find((s) => s.code === "grand_total");
    return segment?.value != null ? checkout.formatTotal(segment.value) : checkout.grandTotal;
});
</script>

<template>
    <div class="checkout-page mx-auto w-full max-w-shell px-4 py-10 md:px-8">
        <StepRail
            v-if="!isOnePage"
            class="mb-10"
            :steps="steps"
            :label="t('steps', 'Checkout steps')"
            :done-label="t('stepDone', 'completed')"
            :current-label="t('stepCurrent', 'current step')"
            @go="(s) => checkout.goToStep(s.key)"
        />

        <div class="grid gap-10 lg:grid-cols-[1fr_360px]">
            <OnePageCheckout
                v-if="isOnePage"
                :directory="directory"
                :login-url="loginUrl"
                :labels="labels"
                :identification-labels="identificationLabels"
                :shipping-labels="shippingLabels"
                :payment-labels="paymentLabels"
                :review-labels="reviewLabels"
                :address-labels="addressLabels"
            />
            <section
                v-else
                aria-labelledby="checkout-step-heading"
                class="rounded-edge border border-ash-200 bg-alabaster-raised p-6 md:p-8"
            >
                <h2 id="checkout-step-heading" class="mb-6 font-display text-2xl text-ink">
                    {{ currentStepLabel }}
                </h2>

                <IdentificationStep
                    v-if="checkout.step === CheckoutStep.Identification"
                    :login-url="loginUrl"
                    :labels="identificationLabels"
                />
                <ShippingStep
                    v-else-if="checkout.step === CheckoutStep.Shipping"
                    :directory="directory"
                    :labels="shippingLabels"
                    :address-labels="addressLabels"
                />
                <PaymentStep
                    v-else-if="checkout.step === CheckoutStep.Payment"
                    :directory="directory"
                    :labels="paymentLabels"
                    :address-labels="addressLabels"
                />
                <ReviewStep
                    v-else-if="checkout.step === CheckoutStep.Review"
                    :labels="reviewLabels"
                />
            </section>

            <!-- Placed explicitly: the sibling is an async component that renders
                 nothing until its chunk lands, so auto-placement would seat the
                 summary in the first column and then shift it a full column over. -->
            <aside
                aria-labelledby="checkout-summary-heading"
                class="flex flex-col gap-6 rounded-edge border border-ash-200 bg-alabaster-raised p-6 lg:sticky lg:top-24 lg:col-start-2 lg:row-start-1 lg:max-h-[calc(100dvh-8rem)] lg:self-start lg:overflow-y-auto"
            >
                <h2 id="checkout-summary-heading" class="font-mono text-xs uppercase tracking-label text-ink-soft">
                    {{ t('summary', 'Order summary') }}
                </h2>

                <p v-if="isEmpty" class="text-ink-soft">{{ t('empty', 'Your bag is empty.') }}</p>

                <ul v-else class="divide-y divide-ash-200 border-y border-ash-200">
                    <li v-for="item in checkout.visibleItems" :key="item.id" class="flex gap-3 py-4">
                        <span class="block h-16 w-14 shrink-0 overflow-hidden rounded-edge bg-ash-100">
                            <img
                                v-if="item.image"
                                :src="item.image"
                                :alt="item.name"
                                class="h-full w-full object-cover"
                                loading="lazy"
                                width="56"
                                height="64"
                            >
                        </span>
                        <span class="flex min-w-0 flex-1 flex-col">
                            <span class="truncate text-sm text-ink">{{ item.name }}</span>
                            <ul
                                v-if="item.options && item.options.length"
                                data-item-options
                                class="mt-0.5 flex flex-col gap-0.5 text-xs text-ink-soft"
                            >
                                <li v-for="option in item.options" :key="option.label" class="truncate">
                                    {{ option.label }}: {{ option.value }}
                                </li>
                            </ul>
                            <span class="mt-0.5 font-mono text-xs text-ink-soft">× {{ item.qty }}</span>
                        </span>
                        <span class="shrink-0 font-mono text-sm text-ink">{{ item.rowTotal }}</span>
                    </li>
                </ul>

                <p v-if="checkout.hiddenItemCount > 0" class="font-mono text-xs text-ink-soft">
                    {{ t('moreItems', '+ {count} more item(s)').replace('{count}', String(checkout.hiddenItemCount)) }}
                </p>

                <dl v-if="checkout.totalSegments.length > 0" class="flex flex-col gap-2 font-mono text-sm">
                    <div
                        v-for="seg in checkout.totalSegments"
                        :key="seg.code"
                        class="flex justify-between"
                        :class="seg.code === 'grand_total' ? 'border-t border-ash-200 pt-2 text-base text-ink' : 'text-ink-soft'"
                    >
                        <dt>{{ seg.title }}</dt>
                        <dd>{{ seg.value === null ? '—' : checkout.formatTotal(seg.value) }}</dd>
                    </div>
                </dl>
                <dl v-else class="flex flex-col gap-2 font-mono text-sm">
                    <div class="flex justify-between text-ink-soft">
                        <dt>{{ t('subtotal', 'Subtotal') }}</dt>
                        <dd>{{ checkout.subtotal }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-ash-200 pt-2 text-base text-ink">
                        <dt>{{ t('total', 'Total') }}</dt>
                        <dd>{{ checkout.grandTotal }}</dd>
                    </div>
                </dl>
            </aside>
        </div>

        <div v-if="!isEmpty && checkout.ready" class="checkout-total-bar" data-total-bar>
            <span class="checkout-total-bar__label">
                {{ t('total', 'Total') }}
                <span class="checkout-total-bar__count">{{ t('barItems', '{count} items').replace('{count}', String(checkout.itemCount)) }}</span>
            </span>
            <span class="checkout-total-bar__value">{{ grandTotalLabel }}</span>
        </div>
    </div>
</template>
