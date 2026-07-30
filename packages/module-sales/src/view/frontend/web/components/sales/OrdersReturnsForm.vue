<script setup lang="ts">
import { ref, useId } from "vue";
import Field from "MageObsidian_Storefront::form/Field";
import { getFormKey } from "MageObsidian_Storefront::js/form-key-provider";

// Guest "Orders and Returns" lookup island. Replaces Luma's RequireJS
// `ordersReturns` widget: it toggles between the Email and ZIP identifier field
// driven by the "Find Order By" select, and POSTs natively to the guest view
// controller (no fetch — a full navigation to the order page). Both identifier
// inputs stay mounted and only the active one is shown (and required): the
// controller's compareStoredBillingDataWithInput reads BOTH oar_email and oar_zip
// from the POST, so dropping one would raise an undefined-key error. The form key
// comes from the cookie, not from the server: this page is cached, so a key
// rendered into it would be a stranger's by the time a visitor submits.
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
        labels?: Labels;
    }>(),
    {
        labels: () => ({}),
    },
);

const t = props.labels;
const findBy = ref("email");
const fieldId = useId();
const id = (field: string): string => `${fieldId}-${field}`;

const findByOptions = [
    { value: "email", label: t.emailOption ?? "" },
    { value: "zip", label: t.zipOption ?? "" },
];
</script>

<template>
    <form :action="props.action" method="post" class="max-w-xl">
        <input type="hidden" name="form_key" :value="getFormKey()" >
        <fieldset class="flex flex-col gap-5">
            <legend class="mb-2 font-display text-lg text-ink">{{ t.legend }}</legend>

            <Field
                :id="id('order-id')"
                :label="t.orderId"
                name="oar_order_id"
                required
            />

            <Field
                :id="id('lastname')"
                :label="t.lastName"
                name="oar_billing_lastname"
                autocomplete="family-name"
                required
            />

            <Field
                :id="id('type')"
                v-model="findBy"
                :label="t.findBy"
                name="oar_type"
                type="select"
                :options="findByOptions"
            />

            <Field
                v-show="findBy === 'email'"
                :id="id('email')"
                :label="t.email"
                name="oar_email"
                type="email"
                autocomplete="email"
                :required="findBy === 'email'"
            />

            <Field
                v-show="findBy === 'zip'"
                :id="id('zip')"
                :label="t.zip"
                name="oar_zip"
                autocomplete="postal-code"
                :required="findBy === 'zip'"
            />

            <div>
                <button
                    type="submit"
                    class="btn btn--solid"
                >
                    {{ t.submit }}
                </button>
            </div>
        </fieldset>
    </form>
</template>
