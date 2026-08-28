<script setup lang="ts">
import { defineAsyncComponent, markRaw, onErrorCaptured, ref } from "vue";

const props = defineProps<{
    code: string;
    title: string;
    component: string;
    selected: boolean;
    config: Record<string, unknown>;
    customerData: Record<string, unknown>;
}>();

const emit = defineEmits<{
    (event: "select"): void;
    (event: "failed", code: string): void;
    (event: "state", patch: Record<string, unknown>): void;
}>();

const broken = ref(false);

const renderer = markRaw(
    defineAsyncComponent(() => import(/* @vite-ignore */ props.component)),
);

onErrorCaptured(() => {
    broken.value = true;
    emit("failed", props.code);
    return false;
});
</script>

<template>
    <component
        :is="renderer"
        v-if="!broken"
        :code="code"
        :title="title"
        :selected="selected"
        :config="config"
        :customer-data="customerData"
        @select="emit('select')"
        @ready="(ready: boolean, reason?: string) => emit('state', { ready, reason: reason ?? '' })"
        @data="(data: Record<string, unknown>) => emit('state', { data })"
        @takeover="(takesOver: boolean) => emit('state', { takesOver })"
    />
</template>
