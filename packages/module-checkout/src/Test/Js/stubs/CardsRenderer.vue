<script setup lang="ts">
import { computed } from "vue";

const props = defineProps<{
    code: string;
    title: string;
    selected: boolean;
    config: Record<string, unknown>;
    customerData: Record<string, unknown>;
}>();

const cards = computed(() =>
    Array.isArray(props.customerData.tokens) ? (props.customerData.tokens as Array<{ publicHash: string; last4: string }>) : [],
);
</script>

<template>
    <ul :data-cards-renderer="code">
        <li v-for="card in cards" :key="card.publicHash" :data-card="card.publicHash">{{ card.last4 }}</li>
    </ul>
</template>
