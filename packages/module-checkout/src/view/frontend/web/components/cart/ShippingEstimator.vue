<!--
 This file is part of the MageObsidian - Checkout project.

 SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 SPDX-License-Identifier: MIT
-->
<script setup lang="ts">
import { computed, ref } from "vue";
import Field from "MageObsidian_Storefront::form/Field";
import {
    useShippingEstimator,
    type EstimatorAddress,
    type ShippingRate,
} from "MageObsidian_Checkout::js/useShippingEstimator";
import type { CheckoutApiConfig } from "MageObsidian_Checkout::js/useCheckoutApi";
import { formatCurrency } from "MageObsidian_Storefront::js/currency";
import {
    basisLabel,
    buildTotalRows,
    normalizeTaxDisplay,
    rateAmounts,
    type TaxBasis,
    type TotalRow,
} from "MageObsidian_Checkout::js/tax-display";

// Cart "Estimate Shipping and Tax" panel (Luma parity). It previews shipping
// rates + recalculated totals for a partial address through the native REST
// endpoints, never mutating the persisted quote — the real selection happens at
// checkout. Lives OUTSIDE the cart's morph region so the bag-refresh leaves it
// intact.
interface RegionOption {
    id: number;
    code: string;
    name: string;
}

interface DirectoryData {
    countries: Array<{ value: string; label: string }>;
    regions: Record<string, RegionOption[]>;
    statesRequired: string[];
    displayAllRegions: boolean;
    defaultCountry: string;
}

interface EstimatorLabels {
    panel?: string;
    country?: string;
    region?: string;
    regionPlaceholder?: string;
    postcode?: string;
    estimate?: string;
    loading?: string;
    methodsHeading?: string;
    noRates?: string;
    free?: string;
    exclTax?: string;
    inclTax?: string;
    subtotalExcl?: string;
    subtotalIncl?: string;
    shippingExcl?: string;
    shippingIncl?: string;
    fixedTax?: string;
}

const props = withDefaults(
    defineProps<{
        config: CheckoutApiConfig;
        directory: DirectoryData;
        currencyFormat?: string;
        taxDisplay?: Record<string, unknown>;
        labels?: EstimatorLabels;
    }>(),
    {
        currencyFormat: "%s",
        taxDisplay: () => ({}),
        labels: () => ({}),
    },
);

const estimator = useShippingEstimator(props.config);

const countryId = ref<string>(props.directory.defaultCountry || "");
const regionId = ref<string>("");
const regionText = ref<string>("");
const postcode = ref<string>("");

const t = (key: keyof EstimatorLabels, fallback: string): string => props.labels?.[key] ?? fallback;

const countryRegions = computed<RegionOption[]>(() => props.directory.regions?.[countryId.value] ?? []);
const hasRegions = computed<boolean>(() => countryRegions.value.length > 0);
const regionOptions = computed(() => [
    { value: "", label: t("regionPlaceholder", "Please select a region") },
    ...countryRegions.value.map((region) => ({ value: String(region.id), label: region.name })),
]);

const formatPrice = (amount: number | null): string =>
    formatCurrency(props.currencyFormat, amount);

const tax = computed(() => normalizeTaxDisplay(props.taxDisplay));
const totalRows = computed<TotalRow[]>(() =>
    buildTotalRows(estimator.totals.value, tax.value, {
        subtotalExcl: props.labels?.subtotalExcl,
        subtotalIncl: props.labels?.subtotalIncl,
        shippingExcl: props.labels?.shippingExcl,
        shippingIncl: props.labels?.shippingIncl,
        fixedTax: props.labels?.fixedTax,
    }),
);
const rateEntries = (rate: ShippingRate) => rateAmounts(rate, tax.value.shipping);
const rateIsFree = (rate: ShippingRate): boolean => rateEntries(rate).every((entry) => !entry.amount);
const rateBasisLabel = (basis: TaxBasis): string =>
    basisLabel(basis, { exclTax: props.labels?.exclTax, inclTax: props.labels?.inclTax });
const totalRowClass = (row: TotalRow): string => {
    if (row.code === "grand_total") {
        return "border-t border-ash-200 pt-2 text-base font-semibold text-ink";
    }
    return row.detail ? "pl-4 text-xs text-ash-500" : "text-ink-soft";
};

function buildAddress(): EstimatorAddress {
    const address: EstimatorAddress = { country_id: countryId.value };
    if (hasRegions.value && regionId.value) {
        const region = countryRegions.value.find((r) => String(r.id) === regionId.value);
        address.region_id = Number(regionId.value);
        if (region) {
            address.region = region.name;
        }
    } else if (regionText.value) {
        address.region = regionText.value;
    }
    if (postcode.value) {
        address.postcode = postcode.value;
    }
    return address;
}

async function onEstimate(): Promise<void> {
    await estimator.estimate(buildAddress());
}

async function onSelect(rate: ShippingRate): Promise<void> {
    await estimator.selectMethod(buildAddress(), rate);
}
</script>

<template>
    <details class="rounded-edge border border-ash-200 px-5 py-4" data-shipping-estimator>
        <summary class="cursor-pointer select-none font-mono text-xs uppercase tracking-eyebrow text-ink-soft">
            {{ t("panel", "Estimate Shipping and Tax") }}
        </summary>

        <div class="mt-5 flex flex-col gap-4">
            <Field
                id="estimator-country"
                v-model="countryId"
                :label="t('country', 'Country')"
                type="select"
                :options="directory.countries"
            />

            <Field
                v-if="hasRegions"
                id="estimator-region"
                v-model="regionId"
                :label="t('region', 'State / Province')"
                type="select"
                :options="regionOptions"
            />
            <Field
                v-else
                id="estimator-region"
                v-model="regionText"
                :label="t('region', 'State / Province')"
            />

            <Field
                id="estimator-postcode"
                v-model="postcode"
                :label="t('postcode', 'ZIP / Postal code')"
                autocomplete="postal-code"
            />

            <button
                type="button"
                :disabled="estimator.loadingRates.value || !countryId"
                class="btn btn--outline btn--sm w-fit"
                @click="onEstimate"
            >
                {{ estimator.loadingRates.value ? t("loading", "Loading…") : t("estimate", "Estimate") }}
            </button>

            <section v-if="estimator.methods.value.length > 0" aria-labelledby="estimator-methods-heading">
                <h3 id="estimator-methods-heading" class="mb-3 font-mono text-xs uppercase tracking-eyebrow text-ink-soft">
                    {{ t("methodsHeading", "Shipping method") }}
                </h3>
                <div class="flex flex-col gap-2" role="radiogroup" :aria-label="t('methodsHeading', 'Shipping method')">
                    <label
                        v-for="rate in estimator.methods.value"
                        :key="estimator.rateKey(rate)"
                        class="field-radio-card flex items-center justify-between gap-4 py-2.5"
                    >
                        <span class="field-radio">
                            <input
                                type="radio"
                                name="estimator-method"
                                class="field-radio__input"
                                :value="estimator.rateKey(rate)"
                                :checked="estimator.selectedKey.value === estimator.rateKey(rate)"
                                @change="onSelect(rate)"
                            >
                            <span class="field-radio__label">
                                {{ rate.carrier_title }}<span v-if="rate.method_title"> — {{ rate.method_title }}</span>
                            </span>
                        </span>
                        <span class="flex flex-col items-end font-mono text-sm text-ink" data-rate-price>
                            <template v-if="rateIsFree(rate)">{{ t("free", "Free") }}</template>
                            <template v-else>
                                <span v-for="entry in rateEntries(rate)" :key="entry.basis">
                                    <span v-if="rateEntries(rate).length > 1" class="text-xs text-ash-500">{{ rateBasisLabel(entry.basis) }}: </span><span :data-basis="entry.basis">{{ formatPrice(entry.amount) }}</span>
                                </span>
                            </template>
                        </span>
                    </label>
                </div>
            </section>

            <dl
                v-if="totalRows.length > 0"
                class="flex flex-col gap-2 border-t border-ash-200 pt-4 font-mono text-sm"
            >
                <div
                    v-for="row in totalRows"
                    :key="`${row.code}-${row.label}`"
                    :data-total-row="row.code"
                    class="flex items-center justify-between gap-4"
                    :class="totalRowClass(row)"
                >
                    <dt :class="row.detail ? '' : 'uppercase tracking-mono'">{{ row.label }}</dt>
                    <dd :class="row.code === 'grand_total' ? 'text-ink' : ''">{{ formatPrice(row.amount) }}</dd>
                </div>
            </dl>

            <p v-if="estimator.error.value" role="alert" class="font-mono text-sm text-sale">{{ estimator.error.value }}</p>
        </div>
    </details>
</template>
