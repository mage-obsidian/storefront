<script setup lang="ts">
import { computed } from "vue";
import Icon from "MageObsidian_ModernFrontend::elements/Icon";
import { useCustomerData } from "MageObsidian_ModernFrontend::js/customer-data";
import { useActivity } from "MageObsidian_ModernFrontend::js/activity";
import { digitNudge } from "MageObsidian_Storefront::js/digitNudge";
import { CART_DOMAIN } from "MageObsidian_Storefront::js/useCart";

// Live bag count for the header. Reads the engine's customer-data bridge, so it
// updates reactively after add-to-cart (and stays FPC-safe — the count is never
// baked into the cached HTML). The shopping-bag icon and badge are decorative; an
// sr-only live region announces changes to assistive tech.
withDefaults(
    defineProps<{
        // i18n-friendly accessible label, e.g. "in your bag" (passed from Twig).
        label?: string;
        syncingLabel?: string;
    }>(),
    { label: "in your bag", syncingLabel: "Updating your bag" },
);

const customerData = useCustomerData();
const activity = useActivity();

const count = computed(() => Number(customerData.section("cart")?.summary_count ?? 0));
const syncing = computed(() => activity.isBusy(CART_DOMAIN));
</script>

<template>
    <span class="cart-count relative inline-flex items-center" :class="{ 'is-syncing': syncing }">
        <Icon name="shopping-bag" set="outline" class="h-5 w-5" />
        <span v-if="syncing" class="cart-count__ring" aria-hidden="true"></span>
        <span
            v-if="count > 0"
            class="cart-count__badge mo-badge"
            aria-hidden="true"
        ><span :style="{ translate: digitNudge(count) }">{{ count }}</span></span>
        <span class="sr-only" role="status" aria-live="polite">
            {{ syncing ? syncingLabel : `${count} ${label}` }}
        </span>
    </span>
</template>
