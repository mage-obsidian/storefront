<script setup lang="ts">
import { ref, onMounted } from "vue";
import { createBundlePrice, type BundleConfig } from "MageObsidian_Catalog::js/bundle-price";

// Live selection total for a bundle. The option controls are server-rendered
// (getOptionHtml) and POST without JS; this island only reads them plus the
// embedded config to show a running estimate. The cart computes the real total.
const props = defineProps<{
    config: string;
    formSelector: string;
    currencyFormat: string;
    label: string;
}>();

const total = ref("");

function parse(json: string): BundleConfig {
    try {
        return (JSON.parse(json) as BundleConfig) ?? {};
    } catch {
        return {};
    }
}

function format(amount: number): string {
    return props.currencyFormat.replace("%s", amount.toFixed(2));
}

onMounted(() => {
    const form = document.querySelector<HTMLElement>(props.formSelector);
    if (!form) {
        return;
    }
    const bundle = createBundlePrice(form, parse(props.config));
    const sync = () => {
        total.value = format(bundle.total());
    };
    bundle.onChange(sync);
    sync();
});
</script>

<template>
    <p v-if="total" class="pdp__bundle-total mt-4 font-mono text-lg text-ink" aria-live="polite">
        {{ label }}: <span>{{ total }}</span>
    </p>
</template>
