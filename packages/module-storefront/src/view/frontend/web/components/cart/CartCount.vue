<script setup lang="ts">
import { computed } from "vue";
import { useCustomerData } from "MageObsidian_ModernFrontend::js/customer-data";

// Live bag count for the header. Reads the engine's customer-data bridge, so it
// updates reactively after add-to-cart (and stays FPC-safe — the count is never
// baked into the cached HTML). The visible badge is decorative; an sr-only live
// region announces changes to assistive tech.
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
        <span
            v-if="count > 0"
            class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-accent px-1 font-mono text-[0.6rem] leading-none text-alabaster"
            aria-hidden="true"
        ><!-- Optical nudge: JetBrains Mono's unused descender leaves a single digit
            sitting ~1px high in the circle; shift the glyph down to center it. -->
            <span class="translate-y-px">{{ count }}</span></span>
        <span v-else class="h-1.5 w-1.5 rounded-full bg-ash-400" aria-hidden="true"></span>
        <span class="sr-only" role="status" aria-live="polite">{{ count }} {{ label }}</span>
    </span>
</template>
