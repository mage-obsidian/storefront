<script setup lang="ts">
// Test stub for the storefront's shared AddressForm
// (`MageObsidian_Storefront::form/AddressForm`), aliased in vitest.config.js.
// It v-models an address and exposes a `validate()` the checkout step calls; here
// validity is driven by a prop so a test can force the valid/invalid branch
// without filling fields. The real form (fields, region reactivity, a11y) is
// covered in module-storefront's AddressForm.test.ts.
import type { AddressData } from "MageObsidian_Storefront::js/address";

const props = withDefaults(
    defineProps<{
        countries?: unknown[];
        regions?: Record<string, unknown>;
        statesRequired?: string[];
        displayAllRegions?: boolean;
        labels?: Record<string, string>;
        valid?: boolean;
    }>(),
    { valid: true },
);

defineModel<AddressData>({ required: true });

function validate(): boolean {
    return props.valid;
}

defineExpose({ validate });
</script>

<template>
    <div data-address-form-stub />
</template>
