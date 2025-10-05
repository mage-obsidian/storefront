<script setup lang="ts">
import { ref, computed } from "vue";
import { createInstantPurchase } from "MageObsidian_InstantPurchase::js/useInstantPurchase";

interface Labels {
    button?: string;
    confirm?: string;
    cancel?: string;
    paying?: string;
    shipping?: string;
    method?: string;
}

const props = withDefaults(
    defineProps<{
        purchaseUrl: string;
        buttonText?: string;
        labels?: Labels;
    }>(),
    { buttonText: "Instant Purchase", labels: () => ({}) },
);

const ip = createInstantPurchase(props.purchaseUrl);
const confirming = ref(false);
const placing = ref(false);
const message = ref("");

const t = (key: keyof Labels, fallback: string): string => props.labels?.[key] ?? fallback;
const label = computed(() => props.buttonText || t("button", "Instant Purchase"));

async function confirm(): Promise<void> {
    placing.value = true;
    message.value = "";
    try {
        const result = await ip.placeOrder();
        message.value = result.message;
        if (result.ok) {
            confirming.value = false;
        }
    } finally {
        placing.value = false;
    }
}
</script>

<template>
    <div v-if="ip.available.value" class="pdp__instant-purchase mt-4">
        <button
            v-if="!confirming"
            type="button"
            class="inline-flex w-full items-center justify-center rounded-edge border border-ink px-8 py-3 font-mono text-[0.72rem] uppercase tracking-[0.18em] text-ink transition-colors hover:bg-ink hover:text-alabaster"
            @click="confirming = true"
        >
            {{ label }}
        </button>

        <div v-else class="rounded-edge border border-ink/40 bg-alabaster-raised p-4">
            <dl class="flex flex-col gap-1 font-mono text-[0.7rem] uppercase tracking-[0.12em] text-ink-soft">
                <div v-if="ip.data.value.paymentToken" class="flex justify-between gap-3">
                    <dt>{{ t("paying", "Paying with") }}</dt>
                    <dd class="text-ink">{{ ip.data.value.paymentToken.summary }}</dd>
                </div>
                <div v-if="ip.data.value.shippingAddress" class="flex justify-between gap-3">
                    <dt>{{ t("shipping", "Shipping to") }}</dt>
                    <dd class="text-ink">{{ ip.data.value.shippingAddress.summary }}</dd>
                </div>
                <div v-if="ip.data.value.shippingMethod" class="flex justify-between gap-3">
                    <dt>{{ t("method", "Via") }}</dt>
                    <dd class="text-ink">{{ ip.data.value.shippingMethod.summary }}</dd>
                </div>
            </dl>
            <div class="mt-4 flex gap-3">
                <button
                    type="button"
                    :disabled="placing"
                    class="inline-flex flex-1 items-center justify-center rounded-edge border border-ink bg-ink px-6 py-2.5 font-mono text-[0.7rem] uppercase tracking-[0.16em] text-alabaster transition-colors hover:bg-transparent hover:text-ink disabled:opacity-60"
                    @click="confirm"
                >
                    {{ placing ? t("paying", "Placing order…") : t("confirm", "Place order now") }}
                </button>
                <button
                    type="button"
                    :disabled="placing"
                    class="font-mono text-[0.7rem] uppercase tracking-[0.16em] text-ink-soft underline-offset-4 hover:text-ink hover:underline disabled:opacity-60"
                    @click="confirming = false"
                >
                    {{ t("cancel", "Cancel") }}
                </button>
            </div>
        </div>

        <p v-if="message" class="mt-2 font-mono text-xs text-ink-soft" role="status">{{ message }}</p>
    </div>
</template>
