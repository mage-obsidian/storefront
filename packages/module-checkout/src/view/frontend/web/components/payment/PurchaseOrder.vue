<!--
 This file is part of the MageObsidian - Checkout project.

 SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 SPDX-License-Identifier: MIT
-->
<script setup lang="ts">
import { computed, ref, useId, watch } from "vue";

const props = defineProps<{
    code: string;
    title: string;
    selected: boolean;
    config: Record<string, unknown>;
    customerData: Record<string, unknown>;
}>();

const emit = defineEmits<{
    (event: "select"): void;
    (event: "ready", ready: boolean, reason?: string): void;
    (event: "data", data: Record<string, unknown>): void;
}>();

const labels = computed(() => (props.config.labels ?? {}) as Record<string, string>);
const number = ref("");
const fieldId = `po-${useId()}`;
const typed = computed(() => number.value.trim());

function publish(): void {
    if (!props.selected) {
        return;
    }
    emit("data", typed.value === "" ? {} : { po_number: typed.value });
    emit(
        "ready",
        typed.value !== "",
        typed.value === "" ? (labels.value.required ?? "Enter the purchase order number.") : "",
    );
}

watch(() => [props.selected, typed.value], publish, { immediate: true });
</script>

<template>
    <div class="flex flex-col gap-3">
        <label class="field-radio-card field-radio">
            <input
                type="radio"
                name="payment-method"
                class="field-radio__input"
                :value="code"
                :checked="selected"
                @change="emit('select')"
            >
            <span class="field-radio__label">{{ title }}</span>
        </label>

        <div v-if="selected" class="field" data-purchase-order>
            <label class="field__label" :for="fieldId">
                {{ labels.poNumber ?? "Purchase Order Number" }}<span class="field__required" aria-hidden="true">*</span>
            </label>
            <input
                :id="fieldId"
                v-model="number"
                type="text"
                class="field__control"
                required
                aria-required="true"
            >
        </div>
    </div>
</template>
