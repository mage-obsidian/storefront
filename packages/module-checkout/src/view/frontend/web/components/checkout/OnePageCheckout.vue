<script setup lang="ts">
import { computed, watch, onBeforeUnmount } from "vue";
import { useCheckout } from "MageObsidian_Checkout::js/useCheckout";
import { missingFields } from "MageObsidian_Storefront::js/address";
import type { RegionData } from "MageObsidian_Storefront::js/address";
import IdentificationStep from "MageObsidian_Checkout::checkout/IdentificationStep";
import ShippingStep from "MageObsidian_Checkout::checkout/ShippingStep";
import PaymentStep from "MageObsidian_Checkout::checkout/PaymentStep";
import ReviewStep from "MageObsidian_Checkout::checkout/ReviewStep";

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

const regionRequired = computed(() => props.directory.statesRequired.includes(checkout.shippingAddress.countryId));

// The shipping estimate only needs country + postcode (+ region where the country
// mandates a state); persisting the address (shipping-information) needs the whole
// form. Gate each REST call on the minimum it requires.
const rateReady = computed(() => {
    const a = checkout.shippingAddress;
    return a.countryId.trim() !== "" && a.postcode.trim() !== "" && (!regionRequired.value || a.region.trim() !== "" || a.regionId !== null);
});
const addressComplete = computed(() => missingFields(checkout.shippingAddress, regionRequired.value).length === 0);
const emailReady = computed(() => checkout.isLoggedIn || checkout.email.trim() !== "");

// The Information stage is "done" once shipping-information persisted — which is
// true iff it returned payment methods. That also gates the reveal of Payment.
const shippingDone = computed(() => checkout.paymentMethods.length > 0);
const paymentReady = shippingDone;

interface StepState {
    key: string;
    label: string;
    target: string;
    done: boolean;
    reachable: boolean;
    active: boolean;
    index: number;
}

// Two stages, matching Magento's native one-step model.
const steps = computed<StepState[]>(() => {
    const raw = [
        { key: "information", label: t("stepInformation", "Information"), target: "onepage-information", done: shippingDone.value, reachable: true },
        { key: "payment", label: t("stepPayment", "Payment"), target: "onepage-payment", done: false, reachable: shippingDone.value },
    ];
    const activeIndex = raw.findIndex((s) => !s.done);
    return raw.map((s, index) => ({ ...s, index, active: index === activeIndex }));
});

const activeKey = computed(() => steps.value.find((s) => s.active)?.key ?? "");

function goTo(step: StepState): void {
    if (!step.reachable) {
        return;
    }
    document.getElementById(step.target)?.scrollIntoView?.({ behavior: "smooth", block: "start" });
}

function addressSignature(): string {
    const a = checkout.shippingAddress;
    return JSON.stringify([a.firstname, a.lastname, a.street, a.city, a.region, a.regionId, a.postcode, a.countryId, a.telephone]);
}

const DEBOUNCE_MS = 400;
let rateTimer: ReturnType<typeof setTimeout> | undefined;
let saveTimer: ReturnType<typeof setTimeout> | undefined;
let lastRateSig = "";
let lastSaveSig = "";

watch(
    () => [rateReady.value, addressSignature()],
    () => {
        if (!rateReady.value) {
            return;
        }
        const sig = addressSignature();
        if (sig === lastRateSig) {
            return;
        }
        clearTimeout(rateTimer);
        rateTimer = setTimeout(() => {
            lastRateSig = sig;
            void checkout.estimateShipping();
        }, DEBOUNCE_MS);
    },
);

watch(
    () => [addressComplete.value, emailReady.value, checkout.selectedMethodKey],
    () => {
        if (!addressComplete.value || !emailReady.value || checkout.selectedMethodKey === "") {
            return;
        }
        const sig = `${addressSignature()}|${checkout.selectedMethodKey}`;
        if (sig === lastSaveSig) {
            return;
        }
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => {
            lastSaveSig = sig;
            void checkout.saveShipping();
        }, DEBOUNCE_MS);
    },
);

onBeforeUnmount(() => {
    clearTimeout(rateTimer);
    clearTimeout(saveTimer);
});
</script>

<template>
    <div class="flex flex-col gap-8">
        <nav
            class="rounded-edge border border-ash-200 bg-alabaster-raised px-5 py-3.5"
            :aria-label="t('steps', 'Checkout steps')"
        >
            <ol class="flex flex-wrap items-center gap-x-8 gap-y-3">
                <li v-for="s in steps" :key="s.key" class="flex items-center gap-2">
                    <button
                        type="button"
                        class="flex items-center gap-2 font-mono text-xs uppercase tracking-[0.16em] transition-colors"
                        :class="[
                            s.done || s.active ? 'text-ink' : 'text-ink-soft',
                            s.reachable ? 'cursor-pointer hover:text-ink' : 'cursor-default',
                        ]"
                        :disabled="!s.reachable"
                        :aria-current="s.active ? 'step' : undefined"
                        @click="goTo(s)"
                    >
                        <span
                            class="flex h-6 w-6 items-center justify-center rounded-full border text-[0.7rem] transition-colors"
                            :class="s.done ? 'border-ink bg-ink text-alabaster' : s.active ? 'border-ink text-ink' : 'border-ash-300 text-ink-soft'"
                            aria-hidden="true"
                        >
                            <svg v-if="s.done" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5">
                                <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" />
                            </svg>
                            <template v-else>{{ s.index + 1 }}</template>
                        </span>
                        {{ s.label }}
                    </button>
                </li>
            </ol>
        </nav>

        <section
            id="onepage-information"
            aria-labelledby="onepage-information-heading"
            class="scroll-mt-20 rounded-edge border bg-alabaster-raised p-6 transition-colors md:p-8"
            :class="activeKey === 'information' ? 'border-ink/40' : 'border-ash-200'"
        >
            <h2 id="onepage-information-heading" class="mb-6 font-display text-2xl text-ink">
                {{ t("stepInformation", "Information") }}
            </h2>
            <div class="flex flex-col gap-8">
                <div v-if="!checkout.isLoggedIn">
                    <h3 class="mb-4 font-mono text-xs uppercase tracking-[0.16em] text-ink-soft">
                        {{ t("contactHeading", "Contact") }}
                    </h3>
                    <IdentificationStep hide-advance :login-url="loginUrl" :labels="identificationLabels" />
                </div>
                <ShippingStep hide-advance :directory="directory" :labels="shippingLabels" :address-labels="addressLabels" />
            </div>
        </section>

        <section
            v-if="paymentReady"
            id="onepage-payment"
            aria-labelledby="onepage-payment-heading"
            class="scroll-mt-20 rounded-edge border bg-alabaster-raised p-6 transition-colors md:p-8"
            :class="activeKey === 'payment' ? 'border-ink/40' : 'border-ash-200'"
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
