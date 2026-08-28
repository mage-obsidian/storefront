<script setup lang="ts">
import { ref, computed } from "vue";
import { useCheckout, CheckoutStep } from "MageObsidian_Checkout::js/useCheckout";
import AddressForm from "MageObsidian_Storefront::form/AddressForm";
import PaymentMethodRenderer from "MageObsidian_Checkout::checkout/PaymentMethodRenderer";
import type { RegionData } from "MageObsidian_Storefront::js/address";

interface DirectoryData {
    countries: Array<{ value: string; label: string }>;
    regions: Record<string, RegionData[]>;
    statesRequired: string[];
    displayAllRegions: boolean;
    defaultCountry: string;
}

interface PaymentLabels {
    methodsHeading?: string;
    billingHeading?: string;
    billingForMethod?: string;
    sameAsShipping?: string;
    noMethods?: string;
    continue?: string;
}

const props = withDefaults(
    defineProps<{
        directory?: DirectoryData;
        labels?: PaymentLabels;
        addressLabels?: Record<string, string>;
        // One-page mode has a single place-order action, so the per-step
        // "Review order" advance button is hidden.
        hideAdvance?: boolean;
    }>(),
    {
        directory: () => ({ countries: [], regions: {}, statesRequired: [], displayAllRegions: false, defaultCountry: "" }),
        labels: () => ({}),
        addressLabels: () => ({}),
        hideAdvance: false,
    },
);

const checkout = useCheckout();
const billingForm = ref<{ validate: () => boolean } | null>(null);
const t = (key: keyof PaymentLabels, fallback: string): string => props.labels?.[key] ?? fallback;

// Native parity for `checkout/options/display_billing_address_on`: value 0
// ("Payment Method", the default) attaches billing to the chosen method, so it
// only surfaces once a method is picked; value 1 ("Payment Page") shows one
// shared form regardless. `displayBillingOnPayment` is true for the per-method case.
const selectedMethodTitle = computed(
    () => checkout.offeredMethods.find((m) => m.code === checkout.selectedPayment)?.title ?? "",
);
const billingVisible = computed(() => !checkout.displayBillingOnPayment || checkout.selectedPayment !== "");

function toReview(): void {
    if (checkout.sameAsShipping || billingForm.value?.validate()) {
        checkout.goToStep(CheckoutStep.Review);
    }
}
</script>

<template>
    <div class="flex flex-col gap-10">
        <section aria-labelledby="payment-methods-heading">
            <h3 id="payment-methods-heading" class="mb-4 font-mono text-xs uppercase tracking-label text-ink-soft">
                {{ t("methodsHeading", "Payment method") }}
            </h3>
            <p v-if="checkout.offeredMethods.length === 0" class="font-mono text-sm text-ink-soft">
                {{ t("noMethods", "No payment methods available.") }}
            </p>
            <div v-else class="flex flex-col gap-3" role="radiogroup" :aria-label="t('methodsHeading', 'Payment method')">
                <template v-for="method in checkout.offeredMethods" :key="method.code">
                    <PaymentMethodRenderer
                        v-if="checkout.rendererFor(method.code)"
                        :data-payment-renderer="method.code"
                        :code="method.code"
                        :title="method.title"
                        :component="checkout.rendererFor(method.code).component"
                        :selected="checkout.selectedPayment === method.code"
                        :config="checkout.configFor(method.code)"
                        :customer-data="checkout.dataFor(method.code)"
                        @select="checkout.selectPayment(method.code)"
                        @state="(patch) => checkout.declareMethodState(method.code, patch)"
                        @failed="checkout.withdrawMethod"
                    />
                    <label v-else class="field-radio-card field-radio">
                        <input
                            type="radio"
                            name="payment-method"
                            class="field-radio__input"
                            :value="method.code"
                            :checked="checkout.selectedPayment === method.code"
                            @change="checkout.selectPayment(method.code)"
                        >
                        <span class="field-radio__label">{{ method.title }}</span>
                    </label>
                </template>
            </div>
        </section>

        <section v-if="billingVisible" aria-labelledby="billing-heading">
            <h3 id="billing-heading" class="mb-4 font-mono text-xs uppercase tracking-label text-ink-soft">
                {{ t("billingHeading", "Billing address") }}
            </h3>
            <p
                v-if="checkout.displayBillingOnPayment && selectedMethodTitle"
                class="mb-4 font-mono text-xs text-ink-soft"
            >
                {{ t("billingForMethod", "For {method}").replace("{method}", selectedMethodTitle) }}
            </p>
            <label class="field-check mb-5">
                <input v-model="checkout.sameAsShipping" type="checkbox" class="field-check__input">
                <span class="field-check__label">{{ t("sameAsShipping", "Same as shipping address") }}</span>
            </label>
            <AddressForm
                v-if="!checkout.sameAsShipping"
                ref="billingForm"
                v-model="checkout.billingAddress"
                :countries="directory.countries"
                :regions="directory.regions"
                :states-required="directory.statesRequired"
                :display-all-regions="directory.displayAllRegions"
                :labels="addressLabels"
            />
        </section>

        <p
            v-if="checkout.selectedPayment && !checkout.selectedMethodReady"
            data-method-blocker
            role="alert"
            class="font-mono text-sm text-sale"
        >
            {{ checkout.selectedMethodBlocker }}
        </p>

        <button
            v-if="!hideAdvance"
            type="button"
            :disabled="!checkout.selectedPayment || !checkout.selectedMethodReady"
            class="checkout-cta btn btn--solid btn--lg btn--block lg:w-fit"
            @click="toReview"
        >
            {{ t("continue", "Review order") }}
        </button>
    </div>
</template>
