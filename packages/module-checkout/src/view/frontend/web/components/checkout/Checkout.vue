<script setup>
import { computed } from "vue";
import { useCheckout } from "MageObsidian_Checkout::js/useCheckout";

// Root of the Vue one-page checkout, replacing Magento's Knockout flow. It mounts
// eager (it IS the page) and is seeded from the server-primed CheckoutConfig, so
// the step rail and order summary paint with zero REST round-trips. Each step's
// UI lands in later milestones; M0 is the shell + summary + the REST auth crux.
const props = defineProps({
    config: { type: Object, default: () => ({}) },
    labels: { type: Object, default: () => ({}) },
});

const checkout = useCheckout();
checkout.init(props.config);

const t = (key, fallback) => props.labels?.[key] ?? fallback;

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
        <ol class="mb-10 flex flex-wrap items-center gap-x-6 gap-y-2" :aria-label="t('steps', 'Checkout steps')">
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
            <section
                aria-labelledby="checkout-step-heading"
                class="rounded-edge border border-ash-200 bg-alabaster-raised p-6 md:p-8"
            >
                <h2 id="checkout-step-heading" class="font-display text-2xl text-ink">
                    {{ currentStepLabel }}
                </h2>
                <p class="mt-3 text-ink-soft">
                    {{ t('stepPlaceholder', 'This step comes online in the next milestone.') }}
                </p>
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

                <dl class="flex flex-col gap-2 font-mono text-sm">
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
