<script setup lang="ts">
import { useCheckout, AGREEMENT_MODE_MANUAL } from "MageObsidian_Checkout::js/useCheckout";

interface AgreementLabels {
    heading?: string;
}

const props = withDefaults(defineProps<{ labels?: AgreementLabels }>(), { labels: () => ({}) });

const checkout = useCheckout();

const t = (key: keyof AgreementLabels, fallback: string): string => props.labels?.[key] ?? fallback;
</script>

<template>
    <section v-if="checkout.agreementsEnabled && checkout.agreements.length > 0" aria-labelledby="agreements-heading">
        <h3 id="agreements-heading" class="mb-4 font-mono text-xs uppercase tracking-[0.16em] text-ink-soft">
            {{ t("heading", "Terms & conditions") }}
        </h3>
        <div class="flex flex-col gap-4">
            <div v-for="agreement in checkout.agreements" :key="agreement.agreementId">
                <div
                    v-if="agreement.content"
                    class="mb-2 overflow-y-auto rounded-edge border border-ash-200 bg-alabaster p-3 text-sm text-ink-soft"
                    :style="{ maxHeight: agreement.contentHeight || '8rem' }"
                    v-html="agreement.content"
                ></div>
                <label
                    v-if="agreement.mode === AGREEMENT_MODE_MANUAL"
                    class="field-check items-start"
                >
                    <input
                        type="checkbox"
                        class="field-check__input mt-0.5"
                        :checked="checkout.acceptedAgreements.includes(agreement.agreementId)"
                        @change="checkout.toggleAgreement(agreement.agreementId)"
                    >
                    <span class="field-check__label normal-case tracking-normal" v-html="agreement.checkboxText"></span>
                </label>
                <p v-else class="text-sm text-ink-soft" v-html="agreement.checkboxText"></p>
            </div>
        </div>
    </section>
</template>
