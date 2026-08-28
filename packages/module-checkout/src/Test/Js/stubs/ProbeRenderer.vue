<script setup lang="ts">
defineProps<{ code: string; title: string; selected: boolean; config: Record<string, unknown> }>();
const emit = defineEmits<{ (event: "select"): void }>();
</script>

<template>
    <button type="button" :data-probe-renderer="code" :data-selected="selected ? '' : null" @click="emit('select')">
        {{ title }}
    </button>
</template>
