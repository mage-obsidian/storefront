<!--
 This file is part of the MageObsidian - InventoryInStorePickup project.

 SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 SPDX-License-Identifier: MIT
-->
<script setup lang="ts">
import { computed, ref, useId, watch } from "vue";
import type { CarrierDestination, ShippingMethod } from "MageObsidian_Checkout::js/useCheckout";
import type { AddressData } from "MageObsidian_Storefront::js/address";
import {
    destinationFor,
    findPickupLocations,
    type PickupLocation,
    type PickupSettings,
} from "MageObsidian_InventoryInStorePickup::js/pickup-locations";

const props = defineProps<{
    code: string;
    methods: ShippingMethod[];
    selected: boolean;
    config: Record<string, unknown>;
    address: AddressData;
    skus: string[];
}>();

const emit = defineEmits<{
    (event: "select", method: ShippingMethod): void;
    (event: "deselect"): void;
    (event: "destination", destination: CarrierDestination | null): void;
}>();

const settings = computed(() => props.config as unknown as PickupSettings);
const labels = computed(() => (props.config.labels ?? {}) as Record<string, string>);
const label = (key: string, fallback: string): string => labels.value[key] ?? fallback;

const groupName = `delivery-${useId()}`;
const locationGroup = `pickup-location-${useId()}`;
const searchId = `pickup-search-${useId()}`;
const query = ref("");
const locations = ref<PickupLocation[]>([]);
const chosen = ref("");
const loading = ref(false);
const failed = ref(false);
const searched = ref(false);

async function search(): Promise<void> {
    loading.value = true;
    failed.value = false;
    try {
        locations.value = await findPickupLocations(
            settings.value,
            query.value,
            props.address.countryId,
            props.skus,
        );
    } catch {
        locations.value = [];
        failed.value = true;
    } finally {
        loading.value = false;
        searched.value = true;
    }
}

function chooseDelivery(pickup: boolean): void {
    if (pickup) {
        emit("select", props.methods[0]);
        return;
    }
    chosen.value = "";
    emit("deselect");
}

function choose(location: PickupLocation): void {
    chosen.value = location.pickup_location_code;
    emit("destination", destinationFor(props.code, location));
}

watch(
    () => props.selected,
    (selected) => {
        if (!selected) {
            chosen.value = "";
            return;
        }
        if (!searched.value) {
            query.value = props.address.postcode ?? "";
            void search();
        }
    },
    { immediate: true },
);

const addressLine = (location: PickupLocation): string =>
    [location.street, location.city, location.postcode].filter((part) => (part ?? "") !== "").join(", ");
</script>

<template>
    <div class="flex flex-col gap-4" data-in-store-pickup>
        <div role="radiogroup" class="flex flex-col gap-3" :aria-label="label('delivery', 'Delivery method')">
            <label class="field-radio-card field-radio" data-delivery-method="ship">
                <input
                    type="radio"
                    class="field-radio__input"
                    :name="groupName"
                    value="ship"
                    :checked="!selected"
                    @change="chooseDelivery(false)"
                >
                <span class="field-radio__label">{{ label("ship", "Ship to my address") }}</span>
            </label>
            <label class="field-radio-card field-radio" data-delivery-method="pickup">
                <input
                    type="radio"
                    class="field-radio__input"
                    :name="groupName"
                    value="pickup"
                    :checked="selected"
                    @change="chooseDelivery(true)"
                >
                <span class="field-radio__label">{{ label("pickup", "Pick up in store") }}</span>
            </label>
        </div>

        <div v-if="selected" class="flex flex-col gap-4">
            <div class="field" data-pickup-search>
                <label class="field__label" :for="searchId">{{ label("search", "Postal code or city") }}</label>
                <div class="flex gap-3">
                    <input
                        :id="searchId"
                        v-model="query"
                        type="search"
                        class="field__control"
                        @keydown.enter.prevent="search"
                    >
                    <button
                        type="button"
                        class="btn btn--outline btn--sm"
                        data-pickup-find
                        :disabled="loading"
                        @click="search"
                    >
                        <span class="btn__label">{{ label("find", "Find pickup locations") }}</span>
                    </button>
                </div>
            </div>

            <div aria-live="polite" data-pickup-status>
                <p v-if="loading" class="text-sm text-ink-soft">{{ label("loading", "Looking for pickup locations…") }}</p>
                <p v-else-if="failed" role="alert" class="text-sm text-sale">
                    {{ label("failed", "We could not load the pickup locations. Try again.") }}
                </p>
                <p v-else-if="searched && locations.length === 0" class="text-sm text-ink-soft">
                    {{ label("none", "No pickup locations found for that search.") }}
                </p>
            </div>

            <div
                v-if="locations.length > 0 && !loading"
                role="radiogroup"
                class="flex flex-col gap-3"
                :aria-label="label('locations', 'Pickup locations')"
            >
                <label
                    v-for="location in locations"
                    :key="location.pickup_location_code"
                    class="field-radio-card field-radio flex-col items-start"
                    :data-pickup-location="location.pickup_location_code"
                >
                    <span class="flex items-center gap-3">
                        <input
                            type="radio"
                            class="field-radio__input"
                            :name="locationGroup"
                            :value="location.pickup_location_code"
                            :checked="chosen === location.pickup_location_code"
                            @change="choose(location)"
                        >
                        <span class="field-radio__label">{{ location.name }}</span>
                    </span>
                    <span class="text-sm text-ink-soft">{{ addressLine(location) }}</span>
                </label>
            </div>
        </div>
    </div>
</template>
