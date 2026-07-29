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
            class="compare-count__badge mo-badge"
            aria-hidden="true"
        ><span :style="{ translate: digitNudge(count) }">{{ count }}</span></span>
        <span class="sr-only" role="status" aria-live="polite">{{ count }} {{ label }}</span>
    </span>
</template>
