<script setup>
import { ref, computed, watch } from "vue";
import { useCart } from "../../js/useCart.js";

// Configurable buy box. Parses core's getJsonConfig / getJsonSwatchConfig and
// owns the selection flow. Stock truth stays server-side — we only grey out
// combinations that have no variant at all; an unbuyable one fails with a toast.
const props = defineProps({
    config: { type: String, required: true },
    swatches: { type: String, default: "{}" },
    productId: { type: [Number, String], required: true },
    action: { type: String, required: true },
    uenc: { type: String, default: "" },
    initialPrice: { type: String, default: "" },
    labels: { type: Object, default: () => ({}) },
});

const TOAST_EVENT = "obsidian:toast";
const VARIANT_EVENT = "obsidian:variant-image";

function parse(json, fallback) {
    try {
        return JSON.parse(json) ?? fallback;
    } catch {
        return fallback;
    }
}

const config = parse(props.config, {});
const swatches = parse(props.swatches, {});
const valid = config && config.attributes && Object.keys(config.attributes).length > 0;

const attributes = valid
    ? Object.values(config.attributes).sort((a, b) => Number(a.position) - Number(b.position))
    : [];

const selected = ref({});
const qty = ref(1);
const adding = ref(false);

const cart = useCart();

/** Variant ids (keys of the index) compatible with a (partial) selection. */
function matchingVariants(selection) {
    return Object.keys(config.index ?? {}).filter((id) =>
        Object.entries(selection).every(
            ([attrId, optionId]) => optionId == null || String(config.index[id][attrId]) === String(optionId),
        ),
    );
}

/** Whether choosing this option still leaves at least one possible variant. */
function isAvailable(attrId, optionId) {
    return matchingVariants({ ...selected.value, [attrId]: optionId }).length > 0;
}

const allSelected = computed(() => attributes.every((attr) => selected.value[attr.id] != null));

const variantId = computed(() => {
    if (!allSelected.value) {
        return null;
    }
    const matches = matchingVariants(selected.value);
    return matches.length ? matches[0] : null;
});

function formatAmount(amount) {
    const fmt = config.currencyFormat ?? "%s";
    return fmt.replace("%s", Number(amount).toFixed(2));
}

const price = computed(() => {
    const prices = variantId.value && config.optionPrices?.[variantId.value];
    return prices ? formatAmount(prices.finalPrice.amount) : props.initialPrice;
});

const oldPrice = computed(() => {
    const prices = variantId.value && config.optionPrices?.[variantId.value];
    if (prices && prices.oldPrice && Number(prices.oldPrice.amount) > Number(prices.finalPrice.amount)) {
        return formatAmount(prices.oldPrice.amount);
    }
    return null;
});

// Swap the gallery's main image when a full variant resolves.
watch(variantId, (id) => {
    if (!id) {
        return;
    }
    const images = config.images?.[id];
    if (Array.isArray(images) && images.length) {
        const main = images.find((image) => image.isMain) ?? images[0];
        window.dispatchEvent(
            new CustomEvent(VARIANT_EVENT, { detail: { large: main.full || main.img, label: main.caption } }),
        );
    }
});

/** Classify a swatch by its value: hex → color, path → image, else text. */
function swatchOf(attrId, optionId) {
    const swatch = swatches?.[attrId]?.[optionId];
    const value = swatch && swatch.value ? String(swatch.value) : "";
    if (value.startsWith("#")) {
        return { kind: "color", value };
    }
    if (value.includes("/")) {
        return { kind: "image", value };
    }
    return { kind: "text" };
}

function labelFor(attr) {
    return (props.labels.chooseFor ?? "Choose %1").replace("%1", attr.label);
}

function select(attrId, optionId) {
    if (!isAvailable(attrId, optionId)) {
        return;
    }
    selected.value = { ...selected.value, [attrId]: optionId };
}

function onKeydown(event, attr, index) {
    const step = event.key === "ArrowRight" || event.key === "ArrowDown" ? 1
        : event.key === "ArrowLeft" || event.key === "ArrowUp" ? -1 : 0;
    if (step === 0) {
        return;
    }
    event.preventDefault();
    const options = attr.options;
    const next = options[(index + step + options.length) % options.length];
    select(attr.id, next.id);
    const group = event.currentTarget.closest("[role=radiogroup]");
    group?.querySelector(`[data-option-id="${next.id}"]`)?.focus();
}

function announce(message, tone) {
    if (message) {
        window.dispatchEvent(new CustomEvent(TOAST_EVENT, { detail: { message, tone } }));
    }
}

async function add() {
    if (!allSelected.value || !variantId.value) {
        announce(props.labels.selectOptions, "error");
        return;
    }
    adding.value = true;
    const superAttribute = {};
    attributes.forEach((attr) => {
        superAttribute[attr.id] = selected.value[attr.id];
    });
    const ok = await cart.addProduct({
        action: props.action,
        product: props.productId,
        qty: qty.value,
        uenc: props.uenc,
        superAttribute,
    });
    announce(ok ? props.labels.added : props.labels.failed, ok ? "success" : "error");
    adding.value = false;
}
</script>

<template>
    <form v-if="valid" class="pdp__configurable" novalidate @submit.prevent="add">
        <div class="pdp__price mb-6 flex items-baseline gap-3" aria-live="polite">
            <span class="pdp__price-final font-mono text-2xl" :class="oldPrice ? 'text-sale' : 'text-ink'">{{ price }}</span>
            <span v-if="oldPrice" class="pdp__price-regular font-mono text-base text-ink-soft line-through">{{ oldPrice }}</span>
        </div>

        <fieldset
            v-for="attr in attributes"
            :key="attr.id"
            class="pdp__swatch-group mb-6 border-0 p-0"
        >
            <legend class="font-mono text-[0.68rem] uppercase tracking-[0.16em] text-ink-soft">
                {{ attr.label }}
            </legend>
            <div
                class="mt-3 flex flex-wrap gap-2"
                role="radiogroup"
                :aria-label="labelFor(attr)"
            >
                <button
                    v-for="(option, index) in attr.options"
                    :key="option.id"
                    type="button"
                    role="radio"
                    :data-option-id="option.id"
                    :aria-checked="selected[attr.id] === option.id"
                    :aria-label="option.label"
                    :disabled="!isAvailable(attr.id, option.id)"
                    :tabindex="selected[attr.id] === option.id || (!selected[attr.id] && index === 0) ? 0 : -1"
                    class="pdp__swatch"
                    :class="{
                        'pdp__swatch--selected': selected[attr.id] === option.id,
                        'pdp__swatch--unavailable': !isAvailable(attr.id, option.id),
                        'pdp__swatch--text': swatchOf(attr.id, option.id).kind === 'text',
                    }"
                    @click="select(attr.id, option.id)"
                    @keydown="onKeydown($event, attr, index)"
                >
                    <span
                        v-if="swatchOf(attr.id, option.id).kind === 'color'"
                        class="pdp__swatch-chip"
                        :style="{ backgroundColor: swatchOf(attr.id, option.id).value }"
                        aria-hidden="true"
                    ></span>
                    <span
                        v-else-if="swatchOf(attr.id, option.id).kind === 'image'"
                        class="pdp__swatch-chip"
                        :style="{ backgroundImage: `url(${swatchOf(attr.id, option.id).value})` }"
                        aria-hidden="true"
                    ></span>
                    <span v-else>{{ option.label }}</span>
                </button>
            </div>
        </fieldset>

        <div class="pdp__buy-row flex flex-wrap items-end gap-4">
            <div class="pdp__qty">
                <label for="pdp-cfg-qty" class="block font-mono text-[0.68rem] uppercase tracking-[0.16em] text-ink-soft">
                    {{ props.labels.qty ?? "Qty" }}
                </label>
                <input
                    id="pdp-cfg-qty"
                    v-model.number="qty"
                    type="number"
                    inputmode="numeric"
                    min="1"
                    step="1"
                    class="mt-2 w-20 rounded-edge border border-ash-300 bg-transparent px-3 py-2.5 text-center font-mono text-sm text-ink focus:border-ink focus:outline-none"
                >
            </div>
            <button
                type="submit"
                :disabled="adding"
                :aria-disabled="!allSelected"
                :aria-busy="adding ? 'true' : 'false'"
                class="inline-flex flex-1 items-center justify-center rounded-edge border border-ink bg-ink px-8 py-3 font-mono text-[0.72rem] uppercase tracking-[0.18em] text-alabaster transition-colors hover:bg-transparent hover:text-ink disabled:opacity-60"
            >
                {{ allSelected ? (props.labels.addToCart ?? "Add to cart") : (props.labels.selectOptions ?? "Select options") }}
            </button>
        </div>
    </form>
</template>
