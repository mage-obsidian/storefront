<script setup lang="ts">
// Test stub for the engine's Icon component
// (`MageObsidian_ModernFrontend::elements/Icon`), aliased in vitest.config.js.
// Renders the same <svg><use> shape so a test can assert an icon is present
// without depending on the sibling module or on the runtime icon base URL.
withDefaults(defineProps<{ name: string; set?: string; size?: number | string }>(), {
    set: "solid",
    size: 24,
});
</script>

<template>
    <svg :width="size" :height="size" aria-hidden="true" :data-icon="name" :data-icon-set="set">
        <use :href="`${size}/${set}/${name}.svg#icon`"></use>
    </svg>
</template>
