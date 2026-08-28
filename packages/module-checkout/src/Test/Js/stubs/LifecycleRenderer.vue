<script setup lang="ts">
import { ref } from "vue";

defineProps<{ code: string; title: string; selected: boolean; config: Record<string, unknown> }>();

const emit = defineEmits<{
    (event: "select"): void;
    (event: "ready", ready: boolean, reason?: string): void;
    (event: "data", data: Record<string, unknown>): void;
    (event: "takeover", takesOver: boolean): void;
}>();

const reference = ref("");

emit("ready", false, "Enter the transfer reference.");

function complete(): void {
    reference.value = "REF-1";
    emit("data", { reference: reference.value });
    emit("ready", true);
}
</script>

<template>
    <div>
        <button type="button" data-lifecycle-select @click="emit('select')">{{ title }}</button>
        <button type="button" data-lifecycle-complete @click="complete">complete</button>
        <button type="button" data-lifecycle-takeover @click="emit('takeover', true)">take over</button>
    </div>
</template>
