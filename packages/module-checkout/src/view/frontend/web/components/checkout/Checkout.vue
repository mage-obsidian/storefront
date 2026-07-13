<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted } from "vue";
import { useCheckout } from "MageObsidian_Checkout::js/useCheckout";
import { useCustomerData } from "MageObsidian_ModernFrontend::js/customer-data";
import type { RegionData } from "MageObsidian_Storefront::js/address";

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
checkout.init({ ...props.config, defaultCountry: props.directory.defaultCountry });

const isOnePage = computed(() => checkout.layout === "onepage");

// The checkout server-primes the authoritative quote, so it is the strongest
// "the cart is exactly this right now" signal there is. Reconcile the shared cart
// section from it on mount so the header badge / mini-cart can never linger on a
// stale count that contradicts what checkout will actually order.
const customerData = useCustomerData();
onMounted(() => {
    customerData.reload(["cart"]);
});

const t = (key: string, fallback: string): string => props.labels?.[key] ?? fallback;

const steps = computed(() => [
    { key: "identification", label: t("stepIdentification", "Identification") },
    { key: "shipping", label: t("stepShipping", "Shipping") },
    { key: "payment", label: t("stepPayment", "Payment") },
    { key: "review", label: t("stepReview", "Review") },
]);

const currentStepLabel = computed(
    () => steps.value.find((s) => s.key === checkout.step)?.label ?? "",
);
const isEmpty = computed(() => checkout.itemCount === 0);
</script>

<template>
    <div class="mx-auto w-full max-w-[1320px] px-4 py-10 md:px-8">
        <ol v-if="!isOnePage" class="mb-10 flex flex-wrap items-center gap-x-6 gap-y-2" :aria-label="t('steps', 'Checkout steps')">
            <li
                v-for="(s, i) in steps"
                :key="s.key"
                class="flex items-center gap-2 font-mono text-xs uppercase tracking-[0.16em]"
                :class="s.key === checkout.step ? 'text-ink' : 'text-ink-soft'"
                :aria-current="s.key === checkout.step ? 'step' : undefined"
            >
                <span
                    class="flex h-6 w-6 items-center justify-center rounded-full border text-[0.7rem]"
                    :class="s.key === checkout.step ? 'border-ink bg-ink text-alabaster' : 'border-ash-300'"
                    aria-hidden="true"
                >{{ i + 1 }}</span>
                {{ s.label }}
            </li>
        </ol>

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
                    v-if="checkout.step === 'identification'"
                    :login-url="loginUrl"
                    :labels="identificationLabels"
                />
                <ShippingStep
                    v-else-if="checkout.step === 'shipping'"
                    :directory="directory"
                    :labels="shippingLabels"
                    :address-labels="addressLabels"
                />
                <PaymentStep
                    v-else-if="checkout.step === 'payment'"
                    :directory="directory"
                    :labels="paymentLabels"
                    :address-labels="addressLabels"
                />
                <ReviewStep
                    v-else-if="checkout.step === 'review'"
                    :labels="reviewLabels"
                />
            </section>

            <aside
                aria-labelledby="checkout-summary-heading"
                class="flex flex-col gap-6 rounded-edge border border-ash-200 bg-alabaster-raised p-6"
            >
                <h2 id="checkout-summary-heading" class="font-mono text-xs uppercase tracking-[0.16em] text-ink-soft">
                    {{ t('summary', 'Order summary') }}
                </h2>

                <p v-if="isEmpty" class="text-ink-soft">{{ t('empty', 'Your bag is empty.') }}</p>

                <ul v-else class="divide-y divide-ash-200 border-y border-ash-200">
                    <li v-for="item in checkout.items" :key="item.id" class="flex gap-3 py-4">
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
                            <span class="font-mono text-xs text-ink-soft">× {{ item.qty }}</span>
                        </span>
                        <span class="shrink-0 font-mono text-sm text-ink">{{ item.rowTotal }}</span>
                    </li>
                </ul>

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
    </div>
</template>
