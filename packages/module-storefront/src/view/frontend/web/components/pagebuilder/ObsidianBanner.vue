<script setup lang="ts">
import { onMounted, ref } from "vue";
import { enhanceReveal } from "MageObsidian_Storefront::js/reveal-on-interaction";

const props = withDefaults(
    defineProps<{
        heading?: string;
        message?: string;
        buttonLabel?: string;
        buttonUrl?: string;
        overlayColor?: string;
        reveal?: string;
    }>(),
    {
        heading: "",
        message: "",
        buttonLabel: "",
        buttonUrl: "",
        overlayColor: "transparent",
        reveal: "always",
    },
);

const root = ref<HTMLElement | null>(null);

onMounted(() => {
    if (root.value !== null && props.reveal === "hover") {
        enhanceReveal(root.value, window);
    }
});
</script>

<template>
    <div
        ref="root"
        data-content-type="banner"
        :data-show-button="props.reveal"
        :data-show-overlay="props.reveal"
    >
        <div class="pagebuilder-banner-wrapper">
            <div class="pagebuilder-overlay pagebuilder-poster-overlay" :data-overlay-color="props.overlayColor">
                <div class="pagebuilder-poster-content">
                    <p v-if="props.heading" class="font-display text-2xl">{{ props.heading }}</p>
                    <p v-if="props.message">{{ props.message }}</p>
                </div>
                <a
                    v-if="props.buttonLabel"
                    class="pagebuilder-banner-button"
                    :href="props.buttonUrl || '#'"
                >{{ props.buttonLabel }}</a>
            </div>
        </div>
    </div>
</template>
