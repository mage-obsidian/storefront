<script setup lang="ts">
import { ref, useTemplateRef } from "vue";
import Field from "MageObsidian_Storefront::form/Field";

// Order cancellation island. Replaces Luma's RequireJS `cancelOrderModal`: a
// "Cancel Order" action that opens an accessible <dialog>, collects a reason and
// submits the `cancelOrder` GraphQL mutation (the order id is base64-encoded into
// a GraphQL ID, mirroring core). The server gates availability via the
// OrderCancellationUi Config ViewModel, so this only mounts when cancellable. On
// success the page reloads to show the new status; errors surface in the dialog.
interface Labels {
    cancel?: string;
    title?: string;
    prompt?: string;
    confirm?: string;
    close?: string;
    submitting?: string;
}

const props = withDefaults(
    defineProps<{
        orderId: number | string;
        realOrderId: string;
        // The reasons config is a keyed map (reason1..reasonN) server-side, so it
        // arrives as an object; normalize to a list of descriptions.
        reasons: string[] | Record<string, string>;
        graphqlUrl: string;
        labels?: Labels;
    }>(),
    {
        labels: () => ({}),
    },
);

const t = props.labels;
const reasonList: string[] = Array.isArray(props.reasons)
    ? props.reasons
    : Object.values(props.reasons);
const dialog = useTemplateRef<HTMLDialogElement>("dialog");
const reason = ref(reasonList[0] ?? "");
const submitting = ref(false);
const error = ref("");

const MUTATION = `mutation cancelOrder($order_id: ID!, $reason: String!) {
  cancelOrder(input: {order_id: $order_id, reason: $reason}) {
    error
    order { status }
  }
}`;

function open(): void {
    error.value = "";
    dialog.value?.showModal();
}

function close(): void {
    dialog.value?.close();
}

async function submit(): Promise<void> {
    submitting.value = true;
    error.value = "";
    try {
        const response = await fetch(props.graphqlUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify({
                query: MUTATION,
                variables: { order_id: btoa(String(props.orderId)), reason: reason.value },
            }),
        });
        const payload = await response.json();
        const result = payload?.data?.cancelOrder;
        if (!result || result.error || payload.errors) {
            error.value = result?.error || payload?.errors?.[0]?.message || "Unable to cancel the order.";
            submitting.value = false;
            return;
        }
        window.location.reload();
    } catch {
        error.value = "Unable to cancel the order.";
        submitting.value = false;
    }
}

const reasonOptions = reasonList.map((r) => ({ value: r, label: r }));
</script>

<template>
    <button
        type="button"
        class="inline-flex h-11 items-center rounded-edge border border-ash-300 px-5 font-mono text-xs uppercase tracking-[0.18em] text-ink-soft transition-colors hover:border-ink hover:text-ink"
        @click="open"
    >
        {{ t.cancel }}
    </button>

    <dialog
        ref="dialog"
        class="w-full max-w-md rounded-edge border border-ash-200 bg-alabaster p-6 text-ink backdrop:bg-ink/40"
        @cancel="close"
    >
        <h2 class="font-display text-lg text-ink">
            {{ t.title }} <span class="text-ink-soft">#{{ props.realOrderId }}</span>
        </h2>

        <Field
            :id="`cancel-reason-${props.orderId}`"
            v-model="reason"
            class="mt-4"
            :label="t.prompt"
            type="select"
            :options="reasonOptions"
        />

        <p v-if="error" role="alert" class="form-banner mt-3">{{ error }}</p>

        <div class="mt-6 flex justify-end gap-4">
            <button
                type="button"
                class="inline-flex h-11 items-center rounded-edge border border-ash-300 px-5 font-mono text-xs uppercase tracking-[0.16em] text-ink-soft transition-colors hover:border-ink hover:text-ink"
                @click="close"
            >
                {{ t.close }}
            </button>
            <button
                type="button"
                :disabled="submitting"
                class="inline-flex h-11 items-center rounded-edge bg-ink px-5 font-mono text-xs uppercase tracking-[0.18em] text-alabaster transition-colors hover:bg-ink-soft disabled:opacity-60"
                @click="submit"
            >
                {{ submitting ? t.submitting : t.confirm }}
            </button>
        </div>
    </dialog>
</template>
