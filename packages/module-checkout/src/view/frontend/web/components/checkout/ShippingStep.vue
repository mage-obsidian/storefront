<script setup lang="ts">
import { ref } from "vue";
import { useCheckout } from "MageObsidian_Checkout::js/useCheckout";
import AddressForm from "MageObsidian_Storefront::form/AddressForm";
import type { RegionData } from "MageObsidian_Storefront::js/address";

// Shipping step: the shared AddressForm (v-model'd to the store address) plus the
// native shipping rates. Estimating only happens once the address validates; the
// returned methods become a radio group, and continuing persists the address +
// method via shipping-information and advances to payment. All data flows through
// the store, so the order summary stays in sync.
interface DirectoryData {
    countries: Array<{ value: string; label: string }>;
    regions: Record<string, RegionData[]>;
    statesRequired: string[];
    displayAllRegions: boolean;
    defaultCountry: string;
}

interface ShippingLabels {
    addressHeading?: string;
    methodsHeading?: string;
    getRates?: string;
    noRates?: string;
    free?: string;
    continue?: string;
    loading?: string;
}

const props = withDefaults(
    defineProps<{
        directory?: DirectoryData;
        labels?: ShippingLabels;
        addressLabels?: Record<string, string>;
    }>(),
    {
        directory: () => ({ countries: [], regions: {}, statesRequired: [], displayAllRegions: false, defaultCountry: "" }),
        labels: () => ({}),
        addressLabels: () => ({}),
    },
);

const checkout = useCheckout();
const addressForm = ref<{ validate: () => boolean } | null>(null);

const t = (key: keyof ShippingLabels, fallback: string): string => props.labels?.[key] ?? fallback;

function formatPrice(amount: number): string {
    if (!amount) {
        return t("free", "Free");
    }
    const format = checkout.currencyFormat || "%s";
    return format.replace("%s", amount.toFixed(2));
}

async function getRates(): Promise<void> {
    if (addressForm.value?.validate()) {
        await checkout.estimateShipping();
    }
}

async function toPayment(): Promise<void> {
    if (addressForm.value?.validate()) {
        await checkout.saveShipping();
    }
}
</script>

<template>
    <div class="flex flex-col gap-10">
        <section aria-labelledby="shipping-address-heading">
            <h3 id="shipping-address-heading" class="mb-5 font-mono text-xs uppercase tracking-[0.16em] text-ink-soft">
                {{ t("addressHeading", "Shipping address") }}
            </h3>
            <AddressForm
                ref="addressForm"
                v-model="checkout.shippingAddress"
                :countries="directory.countries"
                :regions="directory.regions"
                :states-required="directory.statesRequired"
                :display-all-regions="directory.displayAllRegions"
                :labels="addressLabels"
            />
            <button
                type="button"
                :disabled="checkout.loadingRates"
                class="mt-6 inline-flex w-fit items-center justify-center rounded-edge border border-ink px-6 py-2.5 font-mono text-[0.7rem] uppercase tracking-[0.16em] text-ink transition-colors hover:bg-ink hover:text-alabaster disabled:opacity-50"
                @click="getRates"
            >
                {{ checkout.loadingRates ? t("loading", "Loading…") : t("getRates", "Show shipping methods") }}
            </button>
        </section>

        <section v-if="checkout.shippingMethods.length > 0" aria-labelledby="shipping-methods-heading">
            <h3 id="shipping-methods-heading" class="mb-4 font-mono text-xs uppercase tracking-[0.16em] text-ink-soft">
                {{ t("methodsHeading", "Shipping method") }}
            </h3>
            <div class="flex flex-col gap-3" role="radiogroup" :aria-label="t('methodsHeading', 'Shipping method')">
                <div v-for="method in checkout.shippingMethods" :key="`${method.carrier_code}_${method.method_code}`">
                    <label
                        class="flex cursor-pointer items-center justify-between gap-4 rounded-edge border px-4 py-3 transition-colors"
                        :class="checkout.selectedMethodKey === `${method.carrier_code}_${method.method_code}` ? 'border-ink bg-alabaster-raised' : 'border-ash-300 hover:border-ink'"
                    >
                        <span class="flex items-center gap-3">
                            <input
                                type="radio"
                                name="shipping-method"
                                class="h-4 w-4 accent-ink"
                                :value="`${method.carrier_code}_${method.method_code}`"
                                :checked="checkout.selectedMethodKey === `${method.carrier_code}_${method.method_code}`"
                                @change="checkout.selectMethod(method)"
                            >
                            <span class="text-sm text-ink">
                                {{ method.carrier_title }}<span v-if="method.method_title"> — {{ method.method_title }}</span>
                            </span>
                        </span>
                        <span class="font-mono text-sm text-ink">{{ formatPrice(method.amount ?? 0) }}</span>
                    </label>
                </div>
            </div>

            <button
                type="button"
                :disabled="checkout.savingShipping || !checkout.selectedMethod"
                class="mt-8 inline-flex w-fit items-center justify-center rounded-edge border border-ink bg-ink px-8 py-3 font-mono text-[0.72rem] uppercase tracking-[0.18em] text-alabaster transition-colors hover:bg-transparent hover:text-ink disabled:opacity-50"
                @click="toPayment"
            >
                {{ checkout.savingShipping ? t("loading", "Loading…") : t("continue", "Continue to payment") }}
            </button>
        </section>

        <p v-if="checkout.error" role="alert" class="font-mono text-sm text-sale">{{ checkout.error }}</p>
    </div>
</template>
