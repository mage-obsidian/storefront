<script setup lang="ts">
import { computed } from "vue";
import { useCustomerData } from "MageObsidian_ModernFrontend::js/customer-data";

withDefaults(defineProps<{ label?: string }>(), { label: "in your wish list" });

const customerData = useCustomerData();
const count = computed(() => {
    const saved = customerData.section("wishlist")?.saved as Record<string, unknown> | undefined;
    return saved ? Object.keys(saved).length : 0;
});
</script>

<template>
    <span class="wishlist-count relative inline-flex items-center gap-2">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
        </svg>
        <span
            v-if="count > 0"
            class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-accent px-1 font-mono text-[0.6rem] leading-none text-alabaster"
            aria-hidden="true"
        ><span class="translate-y-px">{{ count }}</span></span>
        <span class="sr-only" role="status" aria-live="polite">{{ count }} {{ label }}</span>
    </span>
</template>
