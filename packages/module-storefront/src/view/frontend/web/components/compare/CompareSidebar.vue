<!--
 This file is part of the MageObsidian - Storefront project.

 SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 SPDX-License-Identifier: MIT
-->
<script setup lang="ts">
import { useId } from "vue";
import Icon from "MageObsidian_ModernFrontend::elements/Icon";
import { useCompare } from "MageObsidian_Storefront::js/useCompare";

interface Labels {
    title: string;
    open: string;
    remove: string;
}

const props = defineProps<{ labels: Labels }>();

const compare = useCompare();
const headingId = useId();

const removeLabel = (name = ""): string => props.labels.remove.replace("%1", name.replace(/<[^>]*>/g, ""));
</script>

<template>
    <section v-if="compare.items.value.length > 0" class="block-compare" data-compare-sidebar :aria-labelledby="headingId">
        <h2 :id="headingId" class="font-display text-lg text-ink">{{ labels.title }}</h2>
        <ol class="mt-3 flex flex-col gap-2">
            <li v-for="item in compare.items.value" :key="item.id" class="flex items-start justify-between gap-3">
                <a :href="item.product_url" class="text-sm text-ink hover:text-accent" v-html="item.name"></a>
                <button
                    type="button"
                    class="text-ink-soft hover:text-sale"
                    :aria-label="removeLabel(item.name)"
                    @click="compare.remove(item.id)"
                >
                    <Icon name="x-mark" set="outline" class="h-4 w-4" />
                </button>
            </li>
        </ol>
        <a :href="compare.listUrl.value" class="btn btn--solid btn--sm btn--block mt-4">{{ labels.open }}</a>
    </section>
</template>
