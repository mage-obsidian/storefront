<script setup lang="ts">
import { computed, ref, watch, onBeforeUnmount } from "vue";
import { useCheckout, CheckoutStep } from "MageObsidian_Checkout::js/useCheckout";
import { type RegionData, addressFieldLabel } from "MageObsidian_Storefront::js/address";
import IdentificationStep from "MageObsidian_Checkout::checkout/IdentificationStep";
import ShippingStep from "MageObsidian_Checkout::checkout/ShippingStep";
import PaymentStep from "MageObsidian_Checkout::checkout/PaymentStep";
import ReviewStep from "MageObsidian_Checkout::checkout/ReviewStep";
import StepRail from "MageObsidian_Checkout::checkout/StepRail";

// One-page checkout (Hyvä-style): a two-stage flow on a single screen, mirroring
// Magento's native one-step (1. Information = contact + address + shipping,
// 2. Payment = payment + review + place order). The per-step "Continue" buttons
// are gone; shipping rates and payment populate reactively as the address is
// completed and a method is chosen. A progress stepper reflects each stage's
// state (done / current / pending) so the shopper always knows what is left.
// Static imports on purpose — all sections mount together here.
interface DirectoryData {
    countries: Array<{ value: string; label: string }>;
    regions: Record<string, RegionData[]>;
    statesRequired: string[];
    displayAllRegions: boolean;
    defaultCountry: string;
}

const props = withDefaults(
    defineProps<{
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

const checkout = useCheckout();

const t = (key: string, fallback: string): string => props.labels?.[key] ?? fallback;

// The Information stage is "done" once shipping-information persisted — which is
// true iff it returned payment methods. That also gates the reveal of Payment.
const shippingDone = computed(() => checkout.paymentMethods.length > 0);
const paymentReady = shippingDone;

const shippingStep = ref<{ focusMissingField: (field: string) => void } | null>(null);

const syncing = computed(
    () => checkout.loadingRates || checkout.savingShipping || checkout.shippingSyncPending,
);

const paymentBlockers = computed<Array<{ key: string; label: string }>>(() => {
    if (paymentReady.value || checkout.shippingMethods.length === 0) {
        return [];
    }
    const blockers = checkout.missingAddressFields.map((field) => ({
        key: field,
        label: addressFieldLabel(field, props.addressLabels),
    }));
    if (!checkout.emailReady) {
        blockers.unshift({
            key: "email",
            label: props.identificationLabels?.email ?? "Email address",
        });
    }

    return blockers;
});

const showPaymentPending = computed(
    () =>
        !paymentReady.value &&
        checkout.shippingMethods.length > 0 &&
        (syncing.value || paymentBlockers.value.length > 0),
);

function focusBlocker(key: string): void {
    if (key === "email") {
        document.getElementById("checkout-email")?.focus();
        return;
    }
    shippingStep.value?.focusMissingField(key);
}

interface StepState {
    key: string;
    label: string;
    done: boolean;
    reachable: boolean;
    active: boolean;
    index: number;
}

const informationSection = ref<HTMLElement | null>(null);
const paymentSection = ref<HTMLElement | null>(null);

// Two stages, matching Magento's native one-step model.
const steps = computed<StepState[]>(() => {
    const raw = [
        { key: "information", label: t("stepInformation", "Information"), done: shippingDone.value, reachable: true },
        { key: CheckoutStep.Payment, label: t("stepPayment", "Payment"), done: false, reachable: shippingDone.value },
    ];
    const activeIndex = raw.findIndex((s) => !s.done);
    return raw.map((s, index) => ({ ...s, index, active: index === activeIndex }));
});

const activeKey = computed(() => steps.value.find((s) => s.active)?.key ?? "");

const sectionOf: Record<string, typeof informationSection> = {
    information: informationSection,
    [CheckoutStep.Payment]: paymentSection,
};

function goTo(step: { key: string }): void {
    if (!steps.value.find((s) => s.key === step.key)?.reachable) {
        return;
    }
    sectionOf[step.key]?.value?.scrollIntoView?.({ behavior: "smooth", block: "start" });
}

watch(
    () => [checkout.shippingSignature, checkout.emailReady],
    () => checkout.scheduleShippingSync(),
    { immediate: true },
);

onBeforeUnmount(() => checkout.cancelShippingSync());
</script>

<template>
    <div class="flex flex-col gap-8">
        <StepRail
            :steps="steps"
            :label="t('steps', 'Checkout steps')"
            :done-label="t('stepDone', 'completed')"
            :current-label="t('stepCurrent', 'current step')"
            @go="goTo"
        />

        <section
            id="onepage-information"
            ref="informationSection"
            aria-labelledby="onepage-information-heading"
            class="scroll-mt-20 rounded-edge border bg-alabaster-raised p-6 transition-colors md:p-8"
            :class="activeKey === 'information' ? 'border-ink/40' : 'border-ash-200'"
        >
            <h2 id="onepage-information-heading" class="mb-6 font-display text-2xl text-ink">
                {{ t("stepInformation", "Information") }}
            </h2>
            <div class="flex flex-col gap-8">
                <div v-if="!checkout.isLoggedIn">
                    <h3 class="mb-4 font-mono text-xs uppercase tracking-label text-ink-soft">
                        {{ t("contactHeading", "Contact") }}
                    </h3>
                    <IdentificationStep hide-advance :login-url="loginUrl" :labels="identificationLabels" />
                </div>
                <ShippingStep ref="shippingStep" hide-advance :directory="directory" :labels="shippingLabels" :address-labels="addressLabels" />
            </div>
        </section>

        <section
            v-if="showPaymentPending"
            id="onepage-payment-pending"
            aria-labelledby="onepage-payment-pending-heading"
            class="scroll-mt-20 rounded-edge border border-ash-200 bg-alabaster-raised p-6 transition-colors md:p-8"
        >
            <h2 id="onepage-payment-pending-heading" class="mb-6 font-display text-2xl text-ink">
                {{ t("stepPayment", "Payment") }}
            </h2>
            <div aria-live="polite">
                <p v-if="syncing" class="flex items-center gap-3 text-sm text-ink-soft">
                    <span class="btn__spinner shrink-0" aria-hidden="true"></span>
                    {{ t("paymentConfirming", "Confirming your shipping choice…") }}
                </p>
                <div v-else-if="paymentBlockers.length > 0" class="form-banner" data-payment-blockers role="alert">
                    <p>{{ t("paymentBlockedHeading", "To see the payment methods, complete:") }}</p>
                    <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
                        <li v-for="blocker in paymentBlockers" :key="blocker.key">
                            <button
                                type="button"
                                class="underline underline-offset-4"
                                :data-blocker="blocker.key"
                                @click="focusBlocker(blocker.key)"
                            >
                                {{ blocker.label }}
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <section
            v-if="paymentReady"
            id="onepage-payment"
            ref="paymentSection"
            aria-labelledby="onepage-payment-heading"
            class="scroll-mt-20 rounded-edge border bg-alabaster-raised p-6 transition-colors md:p-8"
            :class="activeKey === CheckoutStep.Payment ? 'border-ink/40' : 'border-ash-200'"
        >
            <h2 id="onepage-payment-heading" class="mb-6 font-display text-2xl text-ink">
                {{ t("stepPayment", "Payment") }}
            </h2>
            <div class="flex flex-col gap-8">
                <PaymentStep hide-advance :directory="directory" :labels="paymentLabels" :address-labels="addressLabels" />
                <ReviewStep :labels="reviewLabels" />
            </div>
        </section>
    </div>
</template>
