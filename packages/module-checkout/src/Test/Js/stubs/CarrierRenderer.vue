<!--
 This file is part of the MageObsidian - Checkout project.

 SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 SPDX-License-Identifier: MIT
-->
<script setup lang="ts">
defineProps<{
    code: string;
    methods: Array<{ carrier_code: string; method_code: string }>;
    selected: boolean;
    config: Record<string, unknown>;
    address: Record<string, unknown>;
    skus: string[];
}>();
const emit = defineEmits<{
    (event: "select", method: { carrier_code: string; method_code: string }): void;
    (event: "deselect"): void;
    (event: "destination", destination: unknown): void;
}>();
</script>

<template>
    <button
        type="button"
        :data-carrier-renderer-probe="code"
        :data-selected="selected ? '' : null"
        :data-skus="skus.join(',')"
        @click="emit('select', methods[0]); emit('destination', { carrierCode: code })"
    >
        {{ methods.length }}
    </button>
</template>
