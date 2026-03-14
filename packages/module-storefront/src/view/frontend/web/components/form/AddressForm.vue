<script setup lang="ts">
import { computed, ref, useId, watch } from "vue";
import {
    type AddressData,
    type RegionData,
    missingFields,
} from "MageObsidian_Storefront::js/address";

// Reusable address form island (the Magento_UI counterpart's address field set):
// name, company, two street lines, city, country, region and postcode/phone. The
// country drives the region control reactively — a directory select when the
// country has predefined regions, a free-text input otherwise — mirroring Luma's
// behaviour from the same server-primed directory data. It is purely presentational
// (v-model'd by the parent step) and exposes `validate()` so the step can gate the
// REST submission; the required-field rules live in the pure `address` helper.
interface CountryOption {
    value: string;
    label: string;
}

interface AddressLabels {
    firstname?: string;
    lastname?: string;
    company?: string;
    street?: string;
    streetLine2?: string;
    city?: string;
    country?: string;
    region?: string;
    regionPlaceholder?: string;
    postcode?: string;
    telephone?: string;
    required?: string;
    optional?: string;
}

const props = withDefaults(
    defineProps<{
        countries?: CountryOption[];
        regions?: Record<string, RegionData[]>;
        statesRequired?: string[];
        displayAllRegions?: boolean;
        labels?: AddressLabels;
    }>(),
    {
        countries: () => [],
        regions: () => ({}),
        statesRequired: () => [],
        displayAllRegions: false,
        labels: () => ({}),
    },
);

const address = defineModel<AddressData>({ required: true });

const errors = ref<Set<string>>(new Set());
const fieldId = useId();
const id = (field: string): string => `${fieldId}-${field}`;
const errorId = (field: string): string => `${fieldId}-${field}-error`;
const hasError = (field: string): boolean => errors.value.has(field);

const t = computed(() => ({
    firstname: props.labels.firstname ?? "First name",
    lastname: props.labels.lastname ?? "Last name",
    company: props.labels.company ?? "Company",
    street: props.labels.street ?? "Street address",
    streetLine2: props.labels.streetLine2 ?? "Apartment, suite, etc.",
    city: props.labels.city ?? "City",
    country: props.labels.country ?? "Country",
    region: props.labels.region ?? "State / Province",
    regionPlaceholder: props.labels.regionPlaceholder ?? "Please select a region",
    postcode: props.labels.postcode ?? "ZIP / Postal code",
    telephone: props.labels.telephone ?? "Phone number",
    required: props.labels.required ?? "This field is required.",
    optional: props.labels.optional ?? "optional",
}));

const availableRegions = computed<RegionData[]>(() => props.regions[address.value.countryId] ?? []);
const hasRegions = computed(() => availableRegions.value.length > 0);
const regionRequired = computed(() => props.statesRequired.includes(address.value.countryId));
const showRegionText = computed(() => !hasRegions.value);

// Keep the region id and the free-text name in lockstep with the selected option
// so the REST mapping has both, and reset the region whenever the country changes
// (a region from the previous country is meaningless under the new one).
watch(
    () => address.value.countryId,
    () => {
        address.value.regionId = null;
        address.value.region = "";
        errors.value.delete("region");
    },
);

function onRegionSelect(event: Event): void {
    const value = Number((event.target as HTMLSelectElement).value);
    const match = availableRegions.value.find((region) => region.id === value);
    address.value.regionId = match ? match.id : null;
    address.value.region = match ? match.name : "";
    if (match) {
        errors.value.delete("region");
    }
}

function clearError(field: string): void {
    if (errors.value.has(field)) {
        const next = new Set(errors.value);
        next.delete(field);
        errors.value = next;
    }
}

/**
 * Validate the required fields; mark the offenders, focus the first one and
 * return whether the address is complete. Called by the parent step before it
 * hits the REST endpoints.
 */
function validate(): boolean {
    const missing = missingFields(address.value, regionRequired.value);
    errors.value = new Set(missing);
    if (missing.length > 0) {
        const first = missing[0] === "street0" ? "street" : missing[0];
        document.getElementById(id(first))?.focus();
        return false;
    }
    return true;
}

defineExpose({ validate });
</script>

<template>
    <div class="grid gap-5 sm:grid-cols-2">
        <div class="flex flex-col gap-1">
            <label :for="id('firstname')" class="font-mono text-[0.68rem] uppercase tracking-[0.16em] text-ink-soft">{{ t.firstname }}</label>
            <input
                :id="id('firstname')"
                v-model="address.firstname"
                type="text"
                autocomplete="given-name"
                :aria-invalid="hasError('firstname') ? 'true' : undefined"
                :aria-describedby="hasError('firstname') ? errorId('firstname') : undefined"
                class="rounded-edge border border-ash-300 bg-transparent px-3 py-2.5 font-mono text-sm text-ink focus:border-ink focus:outline-none"
                @input="clearError('firstname')"
            >
            <p v-if="hasError('firstname')" :id="errorId('firstname')" role="alert" class="font-mono text-[0.66rem] text-sale">{{ t.required }}</p>
        </div>

        <div class="flex flex-col gap-1">
            <label :for="id('lastname')" class="font-mono text-[0.68rem] uppercase tracking-[0.16em] text-ink-soft">{{ t.lastname }}</label>
            <input
                :id="id('lastname')"
                v-model="address.lastname"
                type="text"
                autocomplete="family-name"
                :aria-invalid="hasError('lastname') ? 'true' : undefined"
                :aria-describedby="hasError('lastname') ? errorId('lastname') : undefined"
                class="rounded-edge border border-ash-300 bg-transparent px-3 py-2.5 font-mono text-sm text-ink focus:border-ink focus:outline-none"
                @input="clearError('lastname')"
            >
            <p v-if="hasError('lastname')" :id="errorId('lastname')" role="alert" class="font-mono text-[0.66rem] text-sale">{{ t.required }}</p>
        </div>

        <div class="flex flex-col gap-1 sm:col-span-2">
            <label :for="id('company')" class="font-mono text-[0.68rem] uppercase tracking-[0.16em] text-ink-soft">
                {{ t.company }} <span class="lowercase tracking-normal text-ink-soft">({{ t.optional }})</span>
            </label>
            <input
                :id="id('company')"
                v-model="address.company"
                type="text"
                autocomplete="organization"
                class="rounded-edge border border-ash-300 bg-transparent px-3 py-2.5 font-mono text-sm text-ink focus:border-ink focus:outline-none"
            >
        </div>

        <div class="flex flex-col gap-1 sm:col-span-2">
            <label :for="id('street')" class="font-mono text-[0.68rem] uppercase tracking-[0.16em] text-ink-soft">{{ t.street }}</label>
            <input
                :id="id('street')"
                v-model="address.street[0]"
                type="text"
                autocomplete="address-line1"
                :aria-invalid="hasError('street0') ? 'true' : undefined"
                :aria-describedby="hasError('street0') ? errorId('street0') : undefined"
                class="rounded-edge border border-ash-300 bg-transparent px-3 py-2.5 font-mono text-sm text-ink focus:border-ink focus:outline-none"
                @input="clearError('street0')"
            >
            <input
                v-model="address.street[1]"
                type="text"
                autocomplete="address-line2"
                :aria-label="t.streetLine2"
                :placeholder="t.streetLine2"
                class="mt-2 rounded-edge border border-ash-300 bg-transparent px-3 py-2.5 font-mono text-sm text-ink focus:border-ink focus:outline-none"
            >
            <p v-if="hasError('street0')" :id="errorId('street0')" role="alert" class="font-mono text-[0.66rem] text-sale">{{ t.required }}</p>
        </div>

        <div class="flex flex-col gap-1">
            <label :for="id('countryId')" class="font-mono text-[0.68rem] uppercase tracking-[0.16em] text-ink-soft">{{ t.country }}</label>
            <select
                :id="id('countryId')"
                v-model="address.countryId"
                autocomplete="country"
                :aria-invalid="hasError('countryId') ? 'true' : undefined"
                :aria-describedby="hasError('countryId') ? errorId('countryId') : undefined"
                class="rounded-edge border border-ash-300 bg-transparent px-3 py-2.5 font-mono text-sm text-ink focus:border-ink focus:outline-none"
                @change="clearError('countryId')"
            >
                <option v-for="country in countries" :key="country.value" :value="country.value">{{ country.label }}</option>
            </select>
            <p v-if="hasError('countryId')" :id="errorId('countryId')" role="alert" class="font-mono text-[0.66rem] text-sale">{{ t.required }}</p>
        </div>

        <div class="flex flex-col gap-1">
            <label :for="id('region')" class="font-mono text-[0.68rem] uppercase tracking-[0.16em] text-ink-soft">
                {{ t.region }} <span v-if="!regionRequired" class="lowercase tracking-normal text-ink-soft">({{ t.optional }})</span>
            </label>
            <select
                v-if="hasRegions"
                :id="id('region')"
                :value="address.regionId ?? ''"
                :aria-invalid="hasError('region') ? 'true' : undefined"
                :aria-describedby="hasError('region') ? errorId('region') : undefined"
                class="rounded-edge border border-ash-300 bg-transparent px-3 py-2.5 font-mono text-sm text-ink focus:border-ink focus:outline-none"
                @change="onRegionSelect"
            >
                <option value="">{{ t.regionPlaceholder }}</option>
                <option v-for="region in availableRegions" :key="region.id" :value="region.id">{{ region.name }}</option>
            </select>
            <input
                v-else-if="showRegionText"
                :id="id('region')"
                v-model="address.region"
                type="text"
                autocomplete="address-level1"
                :aria-invalid="hasError('region') ? 'true' : undefined"
                :aria-describedby="hasError('region') ? errorId('region') : undefined"
                class="rounded-edge border border-ash-300 bg-transparent px-3 py-2.5 font-mono text-sm text-ink focus:border-ink focus:outline-none"
                @input="clearError('region')"
            >
            <p v-if="hasError('region')" :id="errorId('region')" role="alert" class="font-mono text-[0.66rem] text-sale">{{ t.required }}</p>
        </div>

        <div class="flex flex-col gap-1">
            <label :for="id('city')" class="font-mono text-[0.68rem] uppercase tracking-[0.16em] text-ink-soft">{{ t.city }}</label>
            <input
                :id="id('city')"
                v-model="address.city"
                type="text"
                autocomplete="address-level2"
                :aria-invalid="hasError('city') ? 'true' : undefined"
                :aria-describedby="hasError('city') ? errorId('city') : undefined"
                class="rounded-edge border border-ash-300 bg-transparent px-3 py-2.5 font-mono text-sm text-ink focus:border-ink focus:outline-none"
                @input="clearError('city')"
            >
            <p v-if="hasError('city')" :id="errorId('city')" role="alert" class="font-mono text-[0.66rem] text-sale">{{ t.required }}</p>
        </div>

        <div class="flex flex-col gap-1">
            <label :for="id('postcode')" class="font-mono text-[0.68rem] uppercase tracking-[0.16em] text-ink-soft">{{ t.postcode }}</label>
            <input
                :id="id('postcode')"
                v-model="address.postcode"
                type="text"
                autocomplete="postal-code"
                :aria-invalid="hasError('postcode') ? 'true' : undefined"
                :aria-describedby="hasError('postcode') ? errorId('postcode') : undefined"
                class="rounded-edge border border-ash-300 bg-transparent px-3 py-2.5 font-mono text-sm text-ink focus:border-ink focus:outline-none"
                @input="clearError('postcode')"
            >
            <p v-if="hasError('postcode')" :id="errorId('postcode')" role="alert" class="font-mono text-[0.66rem] text-sale">{{ t.required }}</p>
        </div>

        <div class="flex flex-col gap-1 sm:col-span-2">
            <label :for="id('telephone')" class="font-mono text-[0.68rem] uppercase tracking-[0.16em] text-ink-soft">{{ t.telephone }}</label>
            <input
                :id="id('telephone')"
                v-model="address.telephone"
                type="tel"
                autocomplete="tel"
                :aria-invalid="hasError('telephone') ? 'true' : undefined"
                :aria-describedby="hasError('telephone') ? errorId('telephone') : undefined"
                class="rounded-edge border border-ash-300 bg-transparent px-3 py-2.5 font-mono text-sm text-ink focus:border-ink focus:outline-none"
                @input="clearError('telephone')"
            >
            <p v-if="hasError('telephone')" :id="errorId('telephone')" role="alert" class="font-mono text-[0.66rem] text-sale">{{ t.required }}</p>
        </div>
    </div>
</template>
