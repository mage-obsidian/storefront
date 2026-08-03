<script setup lang="ts">
import { computed, ref } from "vue";
import { useCheckout } from "MageObsidian_Checkout::js/useCheckout";
import AddressForm from "MageObsidian_Storefront::form/AddressForm";
import Field from "MageObsidian_Storefront::form/Field";
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
    savedAddress?: string;
    newAddress?: string;
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
        // One-page mode drives rate estimation + shipping-information reactively,
        // so the manual "show rates" / "continue" buttons are hidden.
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
const addressForm = ref<{ validate: () => boolean } | null>(null);

const t = (key: keyof ShippingLabels, fallback: string): string => props.labels?.[key] ?? fallback;

const addressOptions = computed(() => [
    ...checkout.savedAddresses.map((address) => ({ value: String(address.id), label: address.label })),
    { value: "", label: t("newAddress", "+ New address") },
]);

function pickAddress(value: string): void {
    checkout.selectAddress(value === "" ? null : Number(value));
}

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
            <div v-if="checkout.savedAddresses.length > 0" data-saved-addresses class="mb-6">
                <Field
                    :id="'checkout-saved-address'"
                    :model-value="checkout.selectedAddressId === null ? '' : String(checkout.selectedAddressId)"
                    :label="t('savedAddress', 'Ship to')"
                    type="select"
                    :options="addressOptions"
                    @update:model-value="pickAddress"
                />
            </div>
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
                v-if="!hideAdvance"
                type="button"
                :disabled="checkout.loadingRates"
                class="btn btn--outline btn--sm mt-6 w-fit"
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
                        class="field-radio-card flex items-center justify-between gap-4"
                    >
                        <span class="field-radio">
                            <input
                                type="radio"
                                name="shipping-method"
                                class="field-radio__input"
                                :value="`${method.carrier_code}_${method.method_code}`"
                                :checked="checkout.selectedMethodKey === `${method.carrier_code}_${method.method_code}`"
                                @change="checkout.selectMethod(method)"
                            >
                            <span class="field-radio__label">
                                {{ method.carrier_title }}<span v-if="method.method_title"> — {{ method.method_title }}</span>
                            </span>
                        </span>
                        <span class="font-mono text-sm text-ink">{{ formatPrice(method.amount ?? 0) }}</span>
                    </label>
                </div>
            </div>

            <button
                v-if="!hideAdvance"
                type="button"
                :disabled="checkout.savingShipping || !checkout.selectedMethod"
                class="btn btn--solid btn--lg mt-8 w-fit"
                @click="toPayment"
            >
                {{ checkout.savingShipping ? t("loading", "Loading…") : t("continue", "Continue to payment") }}
            </button>
        </section>

        <p v-if="checkout.error" role="alert" class="font-mono text-sm text-sale">{{ checkout.error }}</p>
    </div>
</template>
