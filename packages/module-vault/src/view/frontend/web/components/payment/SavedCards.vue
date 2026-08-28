<script setup lang="ts">
import { computed, ref, watch } from "vue";

interface SavedCard {
    publicHash: string;
    methodCode: string;
    last4: string;
    type: string;
    typeLabel: string;
    expiration: string;
}

const props = defineProps<{
    code: string;
    title: string;
    selected: boolean;
    config: Record<string, unknown>;
    customerData: Record<string, unknown>;
}>();

const emit = defineEmits<{
    (event: "select"): void;
    (event: "ready", ready: boolean, reason?: string): void;
    (event: "data", data: Record<string, unknown>): void;
}>();

const cards = computed<SavedCard[]>(() =>
    Array.isArray(props.customerData.tokens) ? (props.customerData.tokens as SavedCard[]) : [],
);
const endingIn = computed(() => String(props.config.endingIn ?? "ending"));

const chosen = ref("");

function choose(publicHash: string): void {
    chosen.value = publicHash;
    emit("select");
}

function publish(): void {
    if (!props.selected) {
        return;
    }
    if (chosen.value === "" && cards.value.length > 0) {
        chosen.value = cards.value[0].publicHash;
    }
    emit("data", { public_hash: chosen.value });
    emit("ready", chosen.value !== "", chosen.value === "" ? "Choose a saved card." : "");
}

watch(() => [props.selected, chosen.value, cards.value.length], publish, { immediate: true });
</script>

<template>
    <div v-if="cards.length > 0" class="flex flex-col gap-3" :data-saved-cards="code">
        <label
            v-for="card in cards"
            :key="card.publicHash"
            class="field-radio-card flex items-center justify-between gap-3"
        >
            <span class="field-radio">
                <input
                    type="radio"
                    name="payment-method"
                    class="field-radio__input"
                    :value="`vault:${card.publicHash}`"
                    :checked="selected && chosen === card.publicHash"
                    @change="choose(card.publicHash)"
                >
                <span class="field-radio__label">
                    {{ card.typeLabel }} {{ endingIn }} {{ card.last4 }}
                </span>
            </span>
            <span class="font-mono text-xs text-ink-soft">{{ card.expiration }}</span>
        </label>
    </div>
</template>
