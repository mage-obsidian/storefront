<script setup lang="ts">
import { computed } from "vue";
import { HeartIcon } from "@heroicons/vue/24/outline";
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
    <span class="wishlist-count relative inline-flex items-center gap-2">
        <HeartIcon class="h-5 w-5" />
        <span
            v-if="count > 0"
            class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-accent px-1 font-body text-[0.65rem] font-medium leading-none text-alabaster"
            aria-hidden="true"
        ><span :style="{ translate: digitNudge(count) }">{{ count }}</span></span>
        <span class="sr-only" role="status" aria-live="polite">{{ count }} {{ label }}</span>
    </span>
</template>
