<script setup lang="ts">
import { ref } from "vue";
import { useCheckout } from "MageObsidian_Checkout::js/useCheckout";
import AddressForm from "MageObsidian_Storefront::form/AddressForm";
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
    savedCardsHeading?: string;
    otherMethodsHeading?: string;
    endingIn?: string;
    billingHeading?: string;
    sameAsShipping?: string;
    noMethods?: string;
    continue?: string;
}

const props = withDefaults(
    defineProps<{
        directory?: DirectoryData;
        labels?: PaymentLabels;
        addressLabels?: Record<string, string>;
    }>(),
    {
        directory: () => ({ countries: [], regions: {}, statesRequired: [], displayAllRegions: false, defaultCountry: "" }),
        labels: () => ({}),
        addressLabels: () => ({}),
    },
);

const checkout = useCheckout();
const billingForm = ref<{ validate: () => boolean } | null>(null);

const t = (key: keyof PaymentLabels, fallback: string): string => props.labels?.[key] ?? fallback;

function toReview(): void {
    if (checkout.sameAsShipping || billingForm.value?.validate()) {
        checkout.goToStep("review");
    }
}
</script>

<template>
    <div class="flex flex-col gap-10">
        <section v-if="checkout.vaultTokens.length > 0" aria-labelledby="saved-cards-heading">
            <h3 id="saved-cards-heading" class="mb-4 font-mono text-xs uppercase tracking-[0.16em] text-ink-soft">
                {{ t("savedCardsHeading", "Saved cards") }}
            </h3>
            <div class="flex flex-col gap-3" role="radiogroup" :aria-label="t('savedCardsHeading', 'Saved cards')">
                <label
                    v-for="token in checkout.vaultTokens"
                    :key="token.publicHash"
                    class="flex cursor-pointer items-center justify-between gap-3 rounded-edge border px-4 py-3 transition-colors"
                    :class="checkout.selectedTokenHash === token.publicHash ? 'border-ink bg-alabaster-raised' : 'border-ash-300 hover:border-ink'"
                >
                    <span class="flex items-center gap-3 text-sm text-ink">
                        <input
                            type="radio"
                            name="payment-method"
                            class="h-4 w-4 accent-ink"
                            :value="`vault:${token.publicHash}`"
                            :checked="checkout.selectedTokenHash === token.publicHash"
                            @change="checkout.selectVaultToken(token.publicHash)"
                        >
                        {{ token.typeLabel }} {{ t("endingIn", "ending") }} {{ token.last4 }}
                    </span>
                    <span class="font-mono text-xs text-ink-soft">{{ token.expiration }}</span>
                </label>
            </div>
        </section>

        <section aria-labelledby="payment-methods-heading">
            <h3 id="payment-methods-heading" class="mb-4 font-mono text-xs uppercase tracking-[0.16em] text-ink-soft">
                {{ checkout.vaultTokens.length > 0 ? t("otherMethodsHeading", "Or pay another way") : t("methodsHeading", "Payment method") }}
            </h3>
            <p v-if="checkout.paymentMethods.length === 0" class="font-mono text-sm text-ink-soft">
                {{ t("noMethods", "No payment methods available.") }}
            </p>
            <div v-else class="flex flex-col gap-3" role="radiogroup" :aria-label="t('methodsHeading', 'Payment method')">
                <label
                    v-for="method in checkout.paymentMethods"
                    :key="method.code"
                    class="flex cursor-pointer items-center gap-3 rounded-edge border px-4 py-3 transition-colors"
                    :class="checkout.selectedPayment === method.code && checkout.selectedTokenHash === '' ? 'border-ink bg-alabaster-raised' : 'border-ash-300 hover:border-ink'"
                >
                    <input
                        type="radio"
                        name="payment-method"
                        class="h-4 w-4 accent-ink"
                        :value="method.code"
                        :checked="checkout.selectedPayment === method.code && checkout.selectedTokenHash === ''"
                        @change="checkout.selectPayment(method.code)"
                    >
                    <span class="text-sm text-ink">{{ method.title }}</span>
                </label>
            </div>
        </section>

        <section aria-labelledby="billing-heading">
            <h3 id="billing-heading" class="mb-4 font-mono text-xs uppercase tracking-[0.16em] text-ink-soft">
                {{ t("billingHeading", "Billing address") }}
            </h3>
            <label class="mb-5 flex cursor-pointer items-center gap-3 text-sm text-ink">
                <input v-model="checkout.sameAsShipping" type="checkbox" class="h-4 w-4 accent-ink">
                {{ t("sameAsShipping", "Same as shipping address") }}
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

        <button
            type="button"
            :disabled="!checkout.selectedPayment"
            class="inline-flex w-fit items-center justify-center rounded-edge border border-ink bg-ink px-8 py-3 font-mono text-[0.72rem] uppercase tracking-[0.18em] text-alabaster transition-colors hover:bg-transparent hover:text-ink disabled:opacity-50"
            @click="toReview"
        >
            {{ t("continue", "Review order") }}
        </button>
    </div>
</template>
