<script setup lang="ts">
import { computed, ref } from "vue";
import {
    useShippingEstimator,
    type EstimatorAddress,
    type ShippingRate,
} from "MageObsidian_Checkout::js/useShippingEstimator";
import type { CheckoutApiConfig } from "MageObsidian_Checkout::js/useCheckoutApi";

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
}

const props = withDefaults(
    defineProps<{
        config: CheckoutApiConfig;
        directory: DirectoryData;
        currencyFormat?: string;
        labels?: EstimatorLabels;
    }>(),
    {
        currencyFormat: "%s",
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

function formatPrice(amount: number | null): string {
    const value = amount ?? 0;
    return (props.currencyFormat || "%s").replace("%s", value.toFixed(2));
}

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
        <summary class="cursor-pointer select-none font-mono text-xs uppercase tracking-[0.14em] text-ink-soft">
            {{ t("panel", "Estimate Shipping and Tax") }}
        </summary>

        <div class="mt-5 flex flex-col gap-4">
            <div class="flex flex-col gap-2">
                <label for="estimator-country" class="font-mono text-xs uppercase tracking-[0.14em] text-ink-soft">{{ t("country", "Country") }}</label>
                <select
                    id="estimator-country"
                    v-model="countryId"
                    class="h-11 rounded-edge border border-ash-300 bg-transparent px-3 text-sm text-ink focus:border-ink focus:outline-none"
                >
                    <option v-for="country in directory.countries" :key="country.value" :value="country.value">{{ country.label }}</option>
                </select>
            </div>

            <div class="flex flex-col gap-2">
                <label for="estimator-region" class="font-mono text-xs uppercase tracking-[0.14em] text-ink-soft">{{ t("region", "State / Province") }}</label>
                <select
                    v-if="hasRegions"
                    id="estimator-region"
                    v-model="regionId"
                    class="h-11 rounded-edge border border-ash-300 bg-transparent px-3 text-sm text-ink focus:border-ink focus:outline-none"
                >
                    <option value="">{{ t("regionPlaceholder", "Please select a region") }}</option>
                    <option v-for="region in countryRegions" :key="region.id" :value="String(region.id)">{{ region.name }}</option>
                </select>
                <input
                    v-else
                    id="estimator-region"
                    v-model="regionText"
                    type="text"
                    class="h-11 rounded-edge border border-ash-300 bg-transparent px-3 text-sm text-ink focus:border-ink focus:outline-none"
                >
            </div>

            <div class="flex flex-col gap-2">
                <label for="estimator-postcode" class="font-mono text-xs uppercase tracking-[0.14em] text-ink-soft">{{ t("postcode", "ZIP / Postal code") }}</label>
                <input
                    id="estimator-postcode"
                    v-model="postcode"
                    type="text"
                    autocomplete="postal-code"
                    class="h-11 rounded-edge border border-ash-300 bg-transparent px-3 text-sm text-ink focus:border-ink focus:outline-none"
                >
            </div>

            <button
                type="button"
                :disabled="estimator.loadingRates.value || !countryId"
                class="inline-flex w-fit items-center justify-center rounded-edge border border-ink px-6 py-2.5 font-mono text-[0.7rem] uppercase tracking-[0.16em] text-ink transition-colors hover:bg-ink hover:text-alabaster disabled:opacity-50"
                @click="onEstimate"
            >
                {{ estimator.loadingRates.value ? t("loading", "Loading…") : t("estimate", "Estimate") }}
            </button>

            <section v-if="estimator.methods.value.length > 0" aria-labelledby="estimator-methods-heading">
                <h3 id="estimator-methods-heading" class="mb-3 font-mono text-xs uppercase tracking-[0.14em] text-ink-soft">
                    {{ t("methodsHeading", "Shipping method") }}
                </h3>
                <div class="flex flex-col gap-2" role="radiogroup" :aria-label="t('methodsHeading', 'Shipping method')">
                    <label
                        v-for="rate in estimator.methods.value"
                        :key="estimator.rateKey(rate)"
                        class="flex cursor-pointer items-center justify-between gap-4 rounded-edge border px-4 py-2.5 transition-colors"
                        :class="estimator.selectedKey.value === estimator.rateKey(rate) ? 'border-ink bg-alabaster-raised' : 'border-ash-300 hover:border-ink'"
                    >
                        <span class="flex items-center gap-3">
                            <input
                                type="radio"
                                name="estimator-method"
                                class="h-4 w-4 accent-ink"
                                :value="estimator.rateKey(rate)"
                                :checked="estimator.selectedKey.value === estimator.rateKey(rate)"
                                @change="onSelect(rate)"
                            >
                            <span class="text-sm text-ink">
                                {{ rate.carrier_title }}<span v-if="rate.method_title"> — {{ rate.method_title }}</span>
                            </span>
                        </span>
                        <span class="font-mono text-sm text-ink">{{ rate.amount ? formatPrice(rate.amount) : t("free", "Free") }}</span>
                    </label>
                </div>
            </section>

            <dl
                v-if="estimator.segments.value.length > 0"
                class="flex flex-col gap-2 border-t border-ash-200 pt-4 font-mono text-sm"
            >
                <div
                    v-for="segment in estimator.segments.value"
                    :key="segment.code"
                    class="flex items-center justify-between gap-4"
                    :class="segment.code === 'grand_total' ? 'border-t border-ash-200 pt-2 text-base font-semibold text-ink' : 'text-ink-soft'"
                >
                    <dt class="uppercase tracking-[0.1em]">{{ segment.title }}</dt>
                    <dd :class="segment.code === 'grand_total' ? 'text-ink' : ''">{{ formatPrice(segment.value) }}</dd>
                </div>
            </dl>

            <p v-if="estimator.error.value" role="alert" class="font-mono text-sm text-sale">{{ estimator.error.value }}</p>
        </div>
    </details>
</template>
