<!--
 This file is part of the MageObsidian - Checkout project.

 SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 SPDX-License-Identifier: MIT
-->
<script setup lang="ts">
import { defineAsyncComponent, markRaw, onErrorCaptured, ref } from "vue";
import type { CarrierDestination, ShippingMethod } from "MageObsidian_Checkout::js/useCheckout";
import type { AddressData } from "MageObsidian_Storefront::js/address";

const props = defineProps<{
    code: string;
    component: string;
    methods: ShippingMethod[];
    selected: boolean;
    config: Record<string, unknown>;
    address: AddressData;
    skus: string[];
}>();

const emit = defineEmits<{
    (event: "select", method: ShippingMethod): void;
    (event: "deselect"): void;
    (event: "destination", destination: CarrierDestination | null): void;
    (event: "failed", code: string): void;
}>();

const broken = ref(false);

const renderer = markRaw(
    defineAsyncComponent(() => import(/* @vite-ignore */ props.component)),
);

onErrorCaptured(() => {
    broken.value = true;
    emit("failed", props.code);
    return false;
});
</script>

<template>
    <component
        :is="renderer"
        v-if="!broken"
        :code="code"
        :methods="methods"
        :selected="selected"
        :config="config"
        :address="address"
        :skus="skus"
        @select="(method: ShippingMethod) => emit('select', method)"
        @deselect="emit('deselect')"
        @destination="(destination: CarrierDestination | null) => emit('destination', destination)"
    />
</template>
