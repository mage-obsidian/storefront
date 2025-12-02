<script setup lang="ts">
import { ref, useId } from "vue";

// Guest "Orders and Returns" lookup island. Replaces Luma's RequireJS
// `ordersReturns` widget: it toggles between the Email and ZIP identifier field
// driven by the "Find Order By" select, and POSTs natively to the guest view
// controller (no fetch — a full navigation to the order page). Both identifier
// inputs stay mounted and only the active one is shown (and required): the
// controller's compareStoredBillingDataWithInput reads BOTH oar_email and oar_zip
// from the POST, so dropping one would raise an undefined-key error. The form key
// is server-primed (the controller is not CSRF-aware).
interface Labels {
    legend?: string;
    orderId?: string;
    lastName?: string;
    findBy?: string;
    email?: string;
    zip?: string;
    emailOption?: string;
    zipOption?: string;
    submit?: string;
}

const props = withDefaults(
    defineProps<{
        action: string;
        formKey: string;
        labels?: Labels;
    }>(),
    {
        labels: () => ({}),
    },
);

const t = props.labels;
const findBy = ref<"email" | "zip">("email");
const fieldId = useId();
const id = (field: string): string => `${fieldId}-${field}`;

const labelClass =
    "font-mono text-[0.68rem] uppercase tracking-[0.16em] text-ink-soft";
const inputClass =
    "rounded-edge border border-ash-300 bg-transparent px-3 py-2.5 font-mono text-sm text-ink focus:border-ink focus:outline-none";
</script>

<template>
    <form :action="props.action" method="post" class="max-w-xl">
        <input type="hidden" name="form_key" :value="props.formKey" >
        <fieldset class="flex flex-col gap-5">
            <legend class="mb-2 font-display text-lg text-ink">{{ t.legend }}</legend>

            <div class="flex flex-col gap-1">
                <label :for="id('order-id')" :class="labelClass">{{ t.orderId }}</label>
                <input
                    :id="id('order-id')"
                    type="text"
                    name="oar_order_id"
                    required
                    :class="inputClass"
                >
            </div>

            <div class="flex flex-col gap-1">
                <label :for="id('lastname')" :class="labelClass">{{ t.lastName }}</label>
                <input
                    :id="id('lastname')"
                    type="text"
                    name="oar_billing_lastname"
                    autocomplete="family-name"
                    required
                    :class="inputClass"
                >
            </div>

            <div class="flex flex-col gap-1">
                <label :for="id('type')" :class="labelClass">{{ t.findBy }}</label>
                <select
                    :id="id('type')"
                    v-model="findBy"
                    name="oar_type"
                    :class="inputClass"
                >
                    <option value="email">{{ t.emailOption }}</option>
                    <option value="zip">{{ t.zipOption }}</option>
                </select>
            </div>

            <div v-show="findBy === 'email'" class="flex flex-col gap-1">
                <label :for="id('email')" :class="labelClass">{{ t.email }}</label>
                <input
                    :id="id('email')"
                    type="email"
                    name="oar_email"
                    autocomplete="email"
                    :required="findBy === 'email'"
                    :class="inputClass"
                >
            </div>

            <div v-show="findBy === 'zip'" class="flex flex-col gap-1">
                <label :for="id('zip')" :class="labelClass">{{ t.zip }}</label>
                <input
                    :id="id('zip')"
                    type="text"
                    name="oar_zip"
                    autocomplete="postal-code"
                    :required="findBy === 'zip'"
                    :class="inputClass"
                >
            </div>

            <div>
                <button
                    type="submit"
                    class="h-11 rounded-edge bg-ink px-6 font-mono text-xs uppercase tracking-[0.18em] text-alabaster transition-colors hover:bg-ink-soft"
                >
                    {{ t.submit }}
                </button>
            </div>
        </fieldset>
    </form>
</template>
