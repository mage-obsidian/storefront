<!--
 This file is part of the MageObsidian - Storefront project.

 SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 SPDX-License-Identifier: MIT
-->
<script setup lang="ts">
import { computed, ref, useId, watch } from "vue";
import Field from "MageObsidian_Storefront::form/Field";
import {
    type AddressData,
    type RegionData,
    type AddressRules,
    ADDRESS_FIELD_LABELS,
    defaultAddressRules,
    missingFields,
    postcodeRequired,
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
    prefix?: string;
    firstname?: string;
    middlename?: string;
    lastname?: string;
    suffix?: string;
    company?: string;
    fax?: string;
    vatId?: string;
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
        invalidFields?: string[];
        rules?: AddressRules;
    }>(),
    {
        countries: () => [],
        regions: () => ({}),
        statesRequired: () => [],
        displayAllRegions: false,
        labels: () => ({}),
        invalidFields: () => [],
        rules: () => defaultAddressRules(),
    },
);

const address = defineModel<AddressData>({ required: true });

const errors = ref<Set<string>>(new Set());
const fieldId = useId();
const id = (field: string): string => `${fieldId}-${field}`;
const errorId = (field: string): string => `${fieldId}-${field}-error`;
const hasError = (field: string): boolean =>
    errors.value.has(field) || props.invalidFields.includes(field);

const t = computed(() => ({
    prefix: props.labels.prefix ?? ADDRESS_FIELD_LABELS.prefix,
    firstname: props.labels.firstname ?? ADDRESS_FIELD_LABELS.firstname,
    middlename: props.labels.middlename ?? ADDRESS_FIELD_LABELS.middlename,
    lastname: props.labels.lastname ?? ADDRESS_FIELD_LABELS.lastname,
    suffix: props.labels.suffix ?? ADDRESS_FIELD_LABELS.suffix,
    company: props.labels.company ?? ADDRESS_FIELD_LABELS.company,
    fax: props.labels.fax ?? ADDRESS_FIELD_LABELS.fax,
    vatId: props.labels.vatId ?? ADDRESS_FIELD_LABELS.vatId,
    street: props.labels.street ?? ADDRESS_FIELD_LABELS.street,
    streetLine2: props.labels.streetLine2 ?? "Apartment, suite, etc.",
    city: props.labels.city ?? ADDRESS_FIELD_LABELS.city,
    country: props.labels.country ?? ADDRESS_FIELD_LABELS.country,
    region: props.labels.region ?? ADDRESS_FIELD_LABELS.region,
    regionPlaceholder: props.labels.regionPlaceholder ?? "Please select a region",
    postcode: props.labels.postcode ?? ADDRESS_FIELD_LABELS.postcode,
    telephone: props.labels.telephone ?? ADDRESS_FIELD_LABELS.telephone,
    required: props.labels.required ?? "This field is required.",
    optional: props.labels.optional ?? "optional",
}));

const rule = (code: string) => props.rules.fields[code] ?? { visible: false, required: false, options: [] };
const listOptions = (code: string): Array<{ value: string; label: string }> => [
    { value: "", label: "" },
    ...rule(code).options.map((option) => ({ value: option, label: option })),
];
const streetLineIndexes = computed(() => Array.from({ length: props.rules.streetLines }, (_, index) => index));
const streetLineLabel = (index: number): string =>
    index === 1 ? t.value.streetLine2 : `${t.value.street} ${index + 1}`;
const postcodeIsRequired = computed(() => postcodeRequired(address.value.countryId, props.rules));

watch(
    () => props.rules.streetLines,
    (lines) => {
        while (address.value.street.length < lines) {
            address.value.street.push("");
        }
    },
    { immediate: true },
);

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

function focusField(field: string): void {
    const control = document.getElementById(id(field === "street0" ? "street" : field));
    control?.scrollIntoView?.({ behavior: "smooth", block: "center" });
    control?.focus();
}

/**
 * Validate the required fields; mark the offenders, focus the first one and
 * return whether the address is complete. Called by the parent step before it
 * hits the REST endpoints.
 */
function validate(): boolean {
    const missing = missingFields(address.value, regionRequired.value, props.rules);
    errors.value = new Set(missing);
    if (missing.length > 0) {
        focusField(missing[0]);
        return false;
    }
    return true;
}

defineExpose({ validate, focusField });
</script>

<template>
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <template v-if="rule('prefix').visible">
            <Field
                v-if="rule('prefix').options.length > 0"
                v-model="address.prefix"
                :id="id('prefix')"
                :label="t.prefix"
                type="select"
                :required="rule('prefix').required"
                autocomplete="honorific-prefix"
                :options="listOptions('prefix')"
                :error="hasError('prefix') ? t.required : ''"
                @update:model-value="clearError('prefix')"
            />
            <Field
                v-else
                v-model="address.prefix"
                :id="id('prefix')"
                :label="t.prefix"
                :required="rule('prefix').required"
                autocomplete="honorific-prefix"
                :error="hasError('prefix') ? t.required : ''"
                @update:model-value="clearError('prefix')"
            />
        </template>

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
            v-if="rule('middlename').visible"
            v-model="address.middlename"
            :id="id('middlename')"
            :label="t.middlename"
            :required="rule('middlename').required"
            autocomplete="additional-name"
            :error="hasError('middlename') ? t.required : ''"
            @update:model-value="clearError('middlename')"
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

        <template v-if="rule('suffix').visible">
            <Field
                v-if="rule('suffix').options.length > 0"
                v-model="address.suffix"
                :id="id('suffix')"
                :label="t.suffix"
                type="select"
                :required="rule('suffix').required"
                autocomplete="honorific-suffix"
                :options="listOptions('suffix')"
                :error="hasError('suffix') ? t.required : ''"
                @update:model-value="clearError('suffix')"
            />
            <Field
                v-else
                v-model="address.suffix"
                :id="id('suffix')"
                :label="t.suffix"
                :required="rule('suffix').required"
                autocomplete="honorific-suffix"
                :error="hasError('suffix') ? t.required : ''"
                @update:model-value="clearError('suffix')"
            />
        </template>

        <div v-if="rule('company').visible" class="sm:col-span-2">
            <Field
                v-model="address.company"
                :id="id('company')"
                :label="t.company"
                :required="rule('company').required"
                autocomplete="organization"
                :error="hasError('company') ? t.required : ''"
                @update:model-value="clearError('company')"
            />
        </div>

        <!-- Two controls under one label, so it is assembled from the same
             `.field*` classes Field emits rather than nesting two of them. -->
        <div class="field sm:col-span-2">
            <label :for="id('street')" class="field__label">
                {{ t.street }}<span class="field__required" aria-hidden="true">*</span>
            </label>
            <template v-for="index in streetLineIndexes" :key="index">
                <input
                    v-if="index === 0"
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
                    v-else
                    v-model="address.street[index]"
                    type="text"
                    class="field__control"
                    :autocomplete="`address-line${index + 1}`"
                    :aria-label="streetLineLabel(index)"
                    :placeholder="streetLineLabel(index)"
                >
            </template>
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
            :required="postcodeIsRequired"
            autocomplete="postal-code"
            :error="hasError('postcode') ? t.required : ''"
            @update:model-value="clearError('postcode')"
        />

        <div v-if="rule('telephone').visible" class="sm:col-span-2">
            <Field
                v-model="address.telephone"
                :id="id('telephone')"
                :label="t.telephone"
                type="tel"
                :required="rule('telephone').required"
                autocomplete="tel"
                :error="hasError('telephone') ? t.required : ''"
                @update:model-value="clearError('telephone')"
            />
        </div>

        <div v-if="rule('fax').visible" class="sm:col-span-2">
            <Field
                v-model="address.fax"
                :id="id('fax')"
                :label="t.fax"
                type="tel"
                :required="rule('fax').required"
                autocomplete="fax tel"
                :error="hasError('fax') ? t.required : ''"
                @update:model-value="clearError('fax')"
            />
        </div>

        <div v-if="rule('vat_id').visible" class="sm:col-span-2" data-field-vat-id>
            <Field
                v-model="address.vatId"
                :id="id('vatId')"
                :label="t.vatId"
                :required="rule('vat_id').required"
                :error="hasError('vatId') ? t.required : ''"
                @update:model-value="clearError('vatId')"
            />
        </div>
    </div>
</template>
