<script setup lang="ts">
import { onMounted, ref } from "vue";
import { enhanceMap } from "MageObsidian_Storefront::js/map";

const props = withDefaults(
    defineProps<{
        latitude?: string | number;
        longitude?: string | number;
        locationName?: string;
        address?: string;
        phone?: string;
    }>(),
    { latitude: "", longitude: "", locationName: "", address: "", phone: "" },
);

const root = ref<HTMLElement | null>(null);

const locations = JSON.stringify([
    {
        position: { latitude: String(props.latitude), longitude: String(props.longitude) },
        location_name: props.locationName,
        address: props.address,
        phone: props.phone,
    },
]);

onMounted(() => {
    if (root.value === null) {
        return;
    }
    enhanceMap(root.value, window, {
        apiKey: root.value.closest("[data-map-api-key]")?.getAttribute("data-map-api-key") ?? "",
    });
});
</script>

<template>
    <div ref="root" data-content-type="map" :data-locations="locations"></div>
</template>
