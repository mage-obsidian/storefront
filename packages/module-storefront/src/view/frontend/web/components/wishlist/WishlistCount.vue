<script setup lang="ts">
import { computed } from "vue";
import Icon from "MageObsidian_ModernFrontend::elements/Icon";
import { useCustomerData } from "MageObsidian_ModernFrontend::js/customer-data";
import { digitNudge } from "MageObsidian_Storefront::js/digitNudge";

withDefaults(defineProps<{ label?: string }>(), { label: "in your wish list" });

const customerData = useCustomerData();
const count = computed(() => {
    const saved = customerData.section("wishlist")?.saved as Record<string, unknown> | undefined;
    return saved ? Object.keys(saved).length : 0;
});
</script>

<template>
    <span class="wishlist-count relative inline-flex items-center">
        <Icon name="heart" set="outline" class="h-5 w-5" />
        <span
            v-if="count > 0"
            class="wishlist-count__badge mo-badge"
            aria-hidden="true"
        ><span :style="{ translate: digitNudge(count) }">{{ count }}</span></span>
        <span class="sr-only" role="status" aria-live="polite">{{ count }} {{ label }}</span>
    </span>
</template>
