<!--
 This file is part of the MageObsidian - Storefront project.

 SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 SPDX-License-Identifier: MIT
-->
<script setup lang="ts">
import { computed, useId } from "vue";
import Icon from "MageObsidian_ModernFrontend::elements/Icon";
import { useCustomerData } from "MageObsidian_ModernFrontend::js/customer-data";
import { getFormKey } from "MageObsidian_Storefront::js/form-key-provider";

interface Labels {
    title: string;
    open: string;
    addToCart: string;
    remove: string;
}

interface PostData {
    action: string;
    data: Record<string, string>;
}

interface WishlistEntry {
    image?: { src?: string; width?: number; height?: number; alt?: string };
    product_id: number | string;
    product_name: string;
    product_url: string;
    product_price?: string;
    product_is_saleable_and_visible?: boolean;
    add_to_cart_params?: string;
    delete_item_params?: string;
}

const props = defineProps<{ labels: Labels; listUrl: string }>();

const customerData = useCustomerData();
const headingId = useId();

function parse(raw?: string): PostData | null {
    try {
        const parsed = JSON.parse(raw ?? "") as Partial<PostData>;
        return parsed.action ? { action: parsed.action, data: parsed.data ?? {} } : null;
    } catch {
        return null;
    }
}

const rows = computed(() => {
    const items = customerData.section("wishlist")?.items;
    const entries = Array.isArray(items) ? (items as WishlistEntry[]) : [];
    return entries.map((entry) => ({
        entry,
        add: entry.product_is_saleable_and_visible ? parse(entry.add_to_cart_params) : null,
        remove: parse(entry.delete_item_params),
    }));
});
const counter = computed(() => String(customerData.section("wishlist")?.counter ?? ""));

const removeLabel = (name: string): string => props.labels.remove.replace("%1", name);
</script>

<template>
    <section v-if="rows.length > 0" class="block-wishlist" data-wishlist-sidebar :aria-labelledby="headingId">
        <h2 :id="headingId" class="font-display text-lg text-ink">
            {{ labels.title }}
            <span v-if="counter" class="ml-1 font-mono text-micro uppercase tracking-eyebrow text-ink-soft">{{ counter }}</span>
        </h2>
        <ol class="mt-3 flex flex-col gap-4">
            <li v-for="{ entry, add, remove } in rows" :key="entry.product_id" class="flex gap-3">
                <a v-if="entry.image?.src" :href="entry.product_url" class="shrink-0" tabindex="-1" aria-hidden="true">
                    <img
                        :src="entry.image.src"
                        :width="entry.image.width"
                        :height="entry.image.height"
                        alt=""
                        loading="lazy"
                        class="h-16 w-16 object-cover"
                    >
                </a>
                <div class="flex min-w-0 flex-1 flex-col gap-1">
                    <a :href="entry.product_url" class="text-sm text-ink hover:text-accent">{{ entry.product_name }}</a>
                    <div v-if="entry.product_price" class="font-mono text-sm text-ink" v-html="entry.product_price"></div>
                    <div class="mt-1 flex items-center gap-3">
                        <template v-if="add">
                            <form :action="add.action" method="post" data-wishlist-add>
                                <input
                                    v-for="(value, name) in add.data"
                                    :key="name"
                                    type="hidden"
                                    :name="String(name)"
                                    :value="value"
                                >
                                <input type="hidden" name="form_key" :value="getFormKey()">
                                <button type="submit" class="btn btn--outline btn--sm">{{ labels.addToCart }}</button>
                            </form>
                        </template>
                        <template v-if="remove">
                            <form :action="remove.action" method="post" data-wishlist-remove>
                                <input
                                    v-for="(value, name) in remove.data"
                                    :key="name"
                                    type="hidden"
                                    :name="String(name)"
                                    :value="value"
                                >
                                <input type="hidden" name="form_key" :value="getFormKey()">
                                <button
                                    type="submit"
                                    class="text-ink-soft hover:text-sale"
                                    :aria-label="removeLabel(entry.product_name)"
                                >
                                    <Icon name="x-mark" set="outline" class="h-4 w-4" />
                                </button>
                            </form>
                        </template>
                    </div>
                </div>
            </li>
        </ol>
        <a :href="listUrl" class="btn btn--solid btn--sm btn--block mt-4">{{ labels.open }}</a>
    </section>
</template>
