<script setup lang="ts">
import { computed, ref, useId, watch } from "vue";
import Field from "MageObsidian_Storefront::form/Field";
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
        <Field
            v-model="address.firstname"
            :id="id('firstname')"
            :label="t.firstname"
            required
            autocomplete="given-name"
            :error="hasError('firstname') ? t.required : ''"
            @update:model-value="clearError('firstname')"
        />

        <Field
            v-model="address.lastname"
            :id="id('lastname')"
            :label="t.lastname"
            required
            autocomplete="family-name"
            :error="hasError('lastname') ? t.required : ''"
            @update:model-value="clearError('lastname')"
        />

        <div class="sm:col-span-2">
            <Field
                v-model="address.company"
                :id="id('company')"
                :label="t.company"
                autocomplete="organization"
            />
        </div>

        <!-- Two controls under one label, so it is assembled from the same
             `.field*` classes Field emits rather than nesting two of them. -->
        <div class="field sm:col-span-2">
            <label :for="id('street')" class="field__label">
                {{ t.street }}<span class="field__required" aria-hidden="true">*</span>
            </label>
            <input
                :id="id('street')"
                v-model="address.street[0]"
                type="text"
                class="field__control"
                autocomplete="address-line1"
                required
                aria-required="true"
                :aria-invalid="hasError('street0') ? 'true' : undefined"
                :aria-describedby="errorId('street0')"
                @input="clearError('street0')"
            >
            <input
                v-model="address.street[1]"
                type="text"
                class="field__control"
                autocomplete="address-line2"
                :aria-label="t.streetLine2"
                :placeholder="t.streetLine2"
            >
            <p :id="errorId('street0')" class="field__error" role="alert">{{ hasError('street0') ? t.required : '' }}</p>
        </div>

        <Field
            v-model="address.countryId"
            :id="id('countryId')"
            :label="t.country"
            type="select"
            required
            autocomplete="country"
            :options="countries"
            :error="hasError('countryId') ? t.required : ''"
            @update:model-value="clearError('countryId')"
        />

        <!-- Region is a select or a free-text input depending on the country, and
             the selection carries both the id and the name. -->
        <div class="field">
            <label :for="id('region')" class="field__label">
                {{ t.region }}<span v-if="regionRequired" class="field__required" aria-hidden="true">*</span>
            </label>
            <select
                v-if="hasRegions"
                :id="id('region')"
                :value="address.regionId ?? ''"
                class="field__control"
                :required="regionRequired"
                :aria-required="regionRequired ? 'true' : undefined"
                :aria-invalid="hasError('region') ? 'true' : undefined"
                :aria-describedby="errorId('region')"
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
                class="field__control"
                autocomplete="address-level1"
                :required="regionRequired"
                :aria-required="regionRequired ? 'true' : undefined"
                :aria-invalid="hasError('region') ? 'true' : undefined"
                :aria-describedby="errorId('region')"
                @input="clearError('region')"
            >
            <p :id="errorId('region')" class="field__error" role="alert">{{ hasError('region') ? t.required : '' }}</p>
        </div>

        <Field
            v-model="address.city"
            :id="id('city')"
            :label="t.city"
            required
            autocomplete="address-level2"
            :error="hasError('city') ? t.required : ''"
            @update:model-value="clearError('city')"
        />

        <Field
            v-model="address.postcode"
            :id="id('postcode')"
            :label="t.postcode"
            required
            autocomplete="postal-code"
            :error="hasError('postcode') ? t.required : ''"
            @update:model-value="clearError('postcode')"
        />

        <div class="sm:col-span-2">
            <Field
                v-model="address.telephone"
                :id="id('telephone')"
                :label="t.telephone"
                type="tel"
                required
                autocomplete="tel"
                :error="hasError('telephone') ? t.required : ''"
                @update:model-value="clearError('telephone')"
            />
        </div>
    </div>
</template>
