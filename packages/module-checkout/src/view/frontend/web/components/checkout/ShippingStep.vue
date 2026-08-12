<script setup lang="ts">
import { computed, ref } from "vue";
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
    savedAddress?: string;
    newAddress?: string;
    methodsHeading?: string;
    getRates?: string;
    noRates?: string;
    ratesHint?: string;
    ratesLoading?: string;
    ratesSaving?: string;
    ratesReady?: string;
    saveAddress?: string;
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
    { value: "", label: t("newAddress", "Use a new address") },
]);

const currentAddressValue = computed(() =>
    checkout.selectedAddressId === null ? "" : String(checkout.selectedAddressId),
);
const showAddressForm = computed(
    () => checkout.savedAddresses.length === 0 || checkout.selectedAddressId === null,
);

function pickAddress(value: string): void {
    checkout.selectAddress(value === "" ? null : Number(value));
}

function addressValid(): boolean {
    return addressForm.value ? addressForm.value.validate() : checkout.selectedAddressId !== null;
}

function formatPrice(amount: number): string {
    if (!amount) {
        return t("free", "Free");
    }
    const format = checkout.currencyFormat || "%s";
    return format.replace("%s", amount.toFixed(2));
}

const hasMethods = computed(() => checkout.shippingMethods.length > 0);
const waitingForAddress = computed(() => !checkout.ratesRequested && !checkout.loadingRates);
const noRatesFound = computed(
    () => checkout.ratesRequested && !checkout.loadingRates && !hasMethods.value && checkout.error === "",
);

async function getRates(): Promise<void> {
    if (addressValid()) {
        await checkout.estimateShipping();
    }
}

async function toPayment(): Promise<void> {
    if (addressValid()) {
        await checkout.saveShipping();
    }
}
</script>

<template>
    <div class="flex flex-col gap-10">
        <section aria-labelledby="shipping-address-heading">
            <h3 id="shipping-address-heading" class="mb-5 font-mono text-xs uppercase tracking-label text-ink-soft">
                {{ t("addressHeading", "Shipping address") }}
            </h3>
            <div
                v-if="checkout.savedAddresses.length > 0"
                data-saved-addresses
                class="mb-6 flex flex-col gap-3"
                role="radiogroup"
                :aria-label="t('savedAddress', 'Ship to')"
            >
                <label
                    v-for="option in addressOptions"
                    :key="option.value"
                    class="field-radio-card field-radio"
                >
                    <input
                        type="radio"
                        name="checkout-saved-address"
                        class="field-radio__input"
                        :value="option.value"
                        :checked="currentAddressValue === option.value"
                        @change="pickAddress(option.value)"
                    >
                    <span class="field-radio__label">{{ option.label }}</span>
                </label>
            </div>

            <div v-if="showAddressForm" data-address-fields class="flex flex-col gap-5">
                <AddressForm
                    ref="addressForm"
                    v-model="checkout.shippingAddress"
                    :countries="directory.countries"
                    :regions="directory.regions"
                    :states-required="directory.statesRequired"
                    :display-all-regions="directory.displayAllRegions"
                    :labels="addressLabels"
                />
                <label v-if="checkout.canSaveAddress" class="field-check" data-save-address>
                    <input v-model="checkout.saveAddress" type="checkbox" class="field-check__input">
                    <span class="field-check__label">{{ t("saveAddress", "Save this address to my address book") }}</span>
                </label>
            </div>
            <button
                v-if="!hideAdvance"
                type="button"
                :disabled="checkout.loadingRates"
                :class="['btn btn--outline btn--sm mt-6 w-fit', checkout.loadingRates && 'is-loading']"
                @click="getRates"
            >
                <span class="btn__label">{{ t("getRates", "Show shipping methods") }}</span>
                <span v-if="checkout.loadingRates" class="btn__spinner" aria-hidden="true"></span>
            </button>
        </section>

        <section aria-labelledby="shipping-methods-heading">
            <h3 id="shipping-methods-heading" class="mb-4 font-mono text-xs uppercase tracking-label text-ink-soft">
                {{ t("methodsHeading", "Shipping method") }}
            </h3>

            <div aria-live="polite" data-rates-status>
                <p v-if="waitingForAddress" class="text-sm text-ink-soft">
                    {{ t("ratesHint", "Complete your address to see the shipping options.") }}
                </p>
                <p v-else-if="checkout.loadingRates" class="flex items-center gap-3 text-sm text-ink-soft">
                    <span class="btn__spinner shrink-0" aria-hidden="true"></span>
                    {{ t("ratesLoading", "Looking for shipping options…") }}
                </p>
                <p v-else-if="checkout.savingShipping" class="flex items-center gap-3 text-sm text-ink-soft">
                    <span class="btn__spinner shrink-0" aria-hidden="true"></span>
                    {{ t("ratesSaving", "Confirming your shipping choice…") }}
                </p>
                <p v-else-if="noRatesFound" class="text-sm text-ink-soft">
                    {{ t("noRates", "No shipping options for this address.") }}
                </p>
                <p v-else-if="hasMethods" class="sr-only">
                    {{ t("ratesReady", "{count} shipping options available.").replace("{count}", String(checkout.shippingMethods.length)) }}
                </p>
            </div>

            <div v-if="hasMethods" class="mt-4 flex flex-col gap-3" role="radiogroup" :aria-label="t('methodsHeading', 'Shipping method')">
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
                v-if="!hideAdvance && hasMethods"
                type="button"
                :disabled="checkout.savingShipping || !checkout.selectedMethod"
                :class="['btn btn--solid btn--lg mt-8 w-fit', checkout.savingShipping && 'is-loading']"
                @click="toPayment"
            >
                <span class="btn__label">{{ t("continue", "Continue to payment") }}</span>
                <span v-if="checkout.savingShipping" class="btn__spinner" aria-hidden="true"></span>
            </button>
        </section>

        <p v-if="checkout.error" role="alert" class="font-mono text-sm text-sale">{{ checkout.error }}</p>
    </div>
</template>
