<script setup>
// Test stub for the storefront's shared Drawer
// (`MageObsidian_Storefront::elements/Drawer`), aliased in vitest.config.js.
// Renders its slot only when `open`, mirroring the real drawer's visibility, and
// exposes the close affordance — enough to assert the mini-cart's own behaviour
// (what it renders, the qty/remove wiring) without the real dialog a11y, which is
// covered in module-storefront's Drawer.test.js.
defineProps({
    open: { type: Boolean, default: false },
    side: { type: String, default: "right" },
    label: { type: String, default: "" },
    id: { type: String, default: "drawer-stub" },
});
const emit = defineEmits(["close"]);
</script>

<template>
    <div v-if="open" :id="id" role="dialog" :aria-label="label" data-drawer-stub>
        <button type="button" data-drawer-close @click="emit('close')">close</button>
        <slot />
    </div>
</template>
