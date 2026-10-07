<!--
 This file is part of the MageObsidian - Checkout project.

 SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 SPDX-License-Identifier: MIT
-->
<script setup lang="ts">
defineProps<{
    options: Array<{ value: string; label: string }>;
    current: string;
    legend: string;
    name: string;
}>();

const emit = defineEmits<{ (event: "pick", value: string): void }>();
</script>

<template>
    <div class="flex flex-col gap-3" role="radiogroup" :aria-label="legend">
        <label v-for="option in options" :key="option.value" class="field-radio-card field-radio">
            <input
                type="radio"
                :name="name"
                class="field-radio__input"
                :value="option.value"
                :checked="current === option.value"
                @change="emit('pick', option.value)"
            >
            <span class="field-radio__label">{{ option.label }}</span>
        </label>
    </div>
</template>
