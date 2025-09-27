<script setup lang="ts">
// Test stub for the storefront's shared Drawer
// (`MageObsidian_Storefront::elements/Drawer`), aliased in vitest.config.js.
// Renders its slot only when `open`, mirroring the real drawer's visibility, and
// exposes the close affordance — enough to assert the mini-cart's own behaviour
// (what it renders, the qty/remove wiring) without the real dialog a11y, which is
// covered in module-storefront's Drawer.test.js.
withDefaults(
    defineProps<{
        open?: boolean;
        side?: string;
        label?: string;
        id?: string;
    }>(),
    { open: false, side: "right", label: "", id: "drawer-stub" },
);
const emit = defineEmits<{ close: [] }>();
</script>

<template>
    <div v-if="open" :id="id" role="dialog" :aria-label="label" data-drawer-stub>
        <button type="button" data-drawer-close @click="emit('close')">close</button>
        <slot />
    </div>
</template>
