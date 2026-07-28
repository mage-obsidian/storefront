<script setup lang="ts">
import { computed } from "vue";
import Icon from "MageObsidian_ModernFrontend::elements/Icon";
import { useCustomerData } from "MageObsidian_ModernFrontend::js/customer-data";
import { digitNudge } from "MageObsidian_Storefront::js/digitNudge";

// Live bag count for the header. Reads the engine's customer-data bridge, so it
// updates reactively after add-to-cart (and stays FPC-safe — the count is never
// baked into the cached HTML). The shopping-bag icon and badge are decorative; an
// sr-only live region announces changes to assistive tech.
withDefaults(
    defineProps<{
        // i18n-friendly accessible label, e.g. "in your bag" (passed from Twig).
        label?: string;
    }>(),
    { label: "in your bag" },
);

const customerData = useCustomerData();
const count = computed(() => Number(customerData.section("cart")?.summary_count ?? 0));
</script>

<template>
    <span class="cart-count relative inline-flex items-center">
        <Icon name="shopping-bag" set="outline" class="h-5 w-5" />
        <span
            v-if="count > 0"
            class="pointer-events-none absolute -right-2 -top-1.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-accent px-1 font-body text-[0.65rem] font-medium leading-none text-alabaster"
            aria-hidden="true"
        ><span :style="{ translate: digitNudge(count) }">{{ count }}</span></span>
        <span class="sr-only" role="status" aria-live="polite">{{ count }} {{ label }}</span>
    </span>
</template>
