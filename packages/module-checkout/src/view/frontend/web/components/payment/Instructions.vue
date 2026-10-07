<!--
 This file is part of the MageObsidian - Checkout project.

 SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 SPDX-License-Identifier: MIT
-->
<script setup lang="ts">
import { computed, watch } from "vue";

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
}>();

interface Block {
    label: string;
    lines: string[];
}

const lines = (value: unknown): string[] =>
    String(value ?? "")
        .split("\n")
        .map((line) => line.trim())
        .filter((line) => line !== "");

const labels = computed(() => (props.config.labels ?? {}) as Record<string, string>);

const blocks = computed<Block[]>(() =>
    [
        { label: labels.value.payableTo ?? "", lines: lines(props.config.payableTo) },
        { label: labels.value.mailingAddress ?? "", lines: lines(props.config.mailingAddress) },
        { label: "", lines: lines(props.config.instructions) },
    ].filter((block) => block.lines.length > 0),
);

watch(
    () => props.selected,
    (selected) => {
        if (selected) {
            emit("ready", true, "");
        }
    },
    { immediate: true },
);
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

        <div
            v-if="selected && blocks.length > 0"
            class="flex flex-col gap-3 border-l border-ash-200 pl-4 font-mono text-sm text-ink-soft"
            :data-payment-instructions="code"
        >
            <p v-for="(block, index) in blocks" :key="index" class="flex flex-col">
                <span v-if="block.label" class="text-xs uppercase tracking-label">{{ block.label }}</span>
                <span v-for="(line, at) in block.lines" :key="at" data-line class="text-ink">{{ line }}</span>
            </p>
        </div>
    </div>
</template>
