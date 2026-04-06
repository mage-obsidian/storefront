<script setup lang="ts">
import { computed } from "vue";
import { useCustomerData } from "MageObsidian_ModernFrontend::js/customer-data";

withDefaults(defineProps<{ label?: string }>(), { label: "in your compare list" });

const customerData = useCustomerData();
const count = computed(() => {
    const items = customerData.section("compare-products")?.items as unknown[] | undefined;
    return Array.isArray(items) ? items.length : 0;
});
</script>

<template>
    <span class="compare-count relative inline-flex items-center gap-2">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 4.5v15m6-15v15m-10.875 0h15.75c.621 0 1.125-.504 1.125-1.125V5.625c0-.621-.504-1.125-1.125-1.125H4.125C3.504 4.5 3 5.004 3 5.625v12.75c0 .621.504 1.125 1.125 1.125Z" />
        </svg>
        <span
            v-if="count > 0"
            class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-accent px-1 font-mono text-[0.6rem] leading-none text-alabaster"
            aria-hidden="true"
        ><span class="translate-y-px">{{ count }}</span></span>
        <span class="sr-only" role="status" aria-live="polite">{{ count }} {{ label }}</span>
    </span>
</template>
