<!--
 This file is part of the MageObsidian - Sales project.

 SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 SPDX-License-Identifier: MIT
-->
<script setup lang="ts">
import { computed, ref, useId } from "vue";
import { useCustomerData } from "MageObsidian_ModernFrontend::js/customer-data";
import { getFormKey } from "MageObsidian_Storefront::js/form-key-provider";

interface Labels {
    title: string;
    subtitle: string;
    addToCart: string;
    viewAll: string;
    unavailable: string;
    choose: string;
}

interface OrderedLine {
    id: number | string;
    name: string;
    url?: string | null;
    is_saleable?: boolean;
}

const props = defineProps<{ labels: Labels; action: string; viewAllUrl: string }>();

const customerData = useCustomerData();
const headingId = useId();
const fieldId = useId();
const missing = ref(false);

const lines = computed<OrderedLine[]>(() => {
    const items = customerData.section("last-ordered-items")?.items;
    return Array.isArray(items) ? (items as OrderedLine[]) : [];
});
const canAdd = computed(() => lines.value.some((line) => line.is_saleable));

function check(event: Event): void {
    const form = event.target as HTMLFormElement;
    const chosen = form.querySelectorAll("input[name='order_items[]']:checked").length;
    missing.value = chosen === 0;
    if (missing.value) {
        event.preventDefault();
    }
}
</script>

<template>
    <section v-if="lines.length > 0" class="block-reorder" data-reorder-sidebar :aria-labelledby="headingId">
        <h2 :id="headingId" class="font-display text-lg text-ink">{{ labels.title }}</h2>
        <form :action="props.action" method="post" class="mt-3" @submit="check">
            <input type="hidden" name="form_key" :value="getFormKey()">
            <p class="font-mono text-micro uppercase tracking-eyebrow text-ink-soft">{{ labels.subtitle }}</p>
            <ol class="mt-2 flex flex-col gap-2">
                <li v-for="line in lines" :key="line.id" class="flex items-start gap-2">
                    <input
                        :id="`${fieldId}-${line.id}`"
                        type="checkbox"
                        name="order_items[]"
                        :value="line.id"
                        :disabled="!line.is_saleable"
                        :title="line.is_saleable ? labels.addToCart : labels.unavailable"
                        class="mt-1"
                    >
                    <label :for="`${fieldId}-${line.id}`" class="text-sm text-ink">
                        <a v-if="line.url" :href="line.url" class="hover:text-accent">{{ line.name }}</a>
                        <span v-else>{{ line.name }}</span>
                    </label>
                </li>
            </ol>
            <p v-if="missing" role="alert" class="mt-2 text-sm text-sale">{{ labels.choose }}</p>
            <div class="mt-4 flex items-center justify-between gap-3">
                <button v-if="canAdd" type="submit" class="btn btn--solid btn--sm">{{ labels.addToCart }}</button>
                <a :href="viewAllUrl" class="view-all font-mono text-micro uppercase tracking-eyebrow text-ink-soft underline">{{ labels.viewAll }}</a>
            </div>
        </form>
    </section>
</template>
