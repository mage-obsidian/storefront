<script setup lang="ts">
import { computed } from "vue";
import Icon from "MageObsidian_ModernFrontend::elements/Icon";
import { useCustomerData } from "MageObsidian_ModernFrontend::js/customer-data";
import { digitNudge } from "MageObsidian_Storefront::js/digitNudge";

withDefaults(defineProps<{ label?: string }>(), { label: "in your compare list" });

const customerData = useCustomerData();
const count = computed(() => {
    const items = customerData.section("compare-products")?.items as unknown[] | undefined;
    return Array.isArray(items) ? items.length : 0;
});
</script>

<template>
    <span class="compare-count relative inline-flex items-center">
        <Icon name="view-columns" set="outline" class="h-5 w-5" />
        <span
            v-if="count > 0"
            class="pointer-events-none absolute -right-2 -top-1.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-accent px-1 font-body text-[0.65rem] font-medium leading-none text-alabaster"
            aria-hidden="true"
        ><span :style="{ translate: digitNudge(count) }">{{ count }}</span></span>
        <span class="sr-only" role="status" aria-live="polite">{{ count }} {{ label }}</span>
    </span>
</template>
