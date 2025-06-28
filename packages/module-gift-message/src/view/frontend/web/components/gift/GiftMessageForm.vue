<script setup lang="ts">
import { ref, watch } from "vue";
import type { GiftMessageData } from "MageObsidian_GiftMessage::js/useGiftMessage";

const props = defineProps<{
    title: string;
    sender: string;
    recipient: string;
    message: string;
    fromLabel: string;
    toLabel: string;
    messageLabel: string;
    saveLabel: string;
    savedLabel: string;
}>();

const emit = defineEmits<{ (e: "save", data: GiftMessageData): void }>();

const sender = ref(props.sender);
const recipient = ref(props.recipient);
const message = ref(props.message);
const busy = ref(false);
const saved = ref(false);
const error = ref("");

// Keep fields in sync if the server-provided initial values change (rare, but
// the parent may re-key on cart changes).
watch(
    () => [props.sender, props.recipient, props.message],
    ([s, r, m]) => {
        sender.value = s;
        recipient.value = r;
        message.value = m;
    },
);

async function submit(): Promise<void> {
    error.value = "";
    saved.value = false;
    busy.value = true;
    try {
        await Promise.resolve(
            emit("save", { sender: sender.value, recipient: recipient.value, message: message.value }),
        );
    } finally {
        busy.value = false;
    }
}

/** Called by the parent to surface the async outcome of a save. */
function resolve(ok: boolean, errorMessage = ""): void {
    saved.value = ok;
    error.value = ok ? "" : errorMessage;
}

defineExpose({ resolve });
</script>

<template>
    <form class="flex flex-col gap-3" @submit.prevent="submit">
        <p class="font-mono text-xs uppercase tracking-[0.16em] text-ink-soft">{{ title }}</p>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="flex flex-col gap-1">
                <span class="font-mono text-[0.7rem] uppercase tracking-[0.14em] text-ink-soft">{{ fromLabel }}</span>
                <input v-model="sender" type="text" class="h-10 rounded-edge border border-ash-300 bg-transparent px-3 text-sm text-ink focus:border-ink focus:outline-none">
            </label>
            <label class="flex flex-col gap-1">
                <span class="font-mono text-[0.7rem] uppercase tracking-[0.14em] text-ink-soft">{{ toLabel }}</span>
                <input v-model="recipient" type="text" class="h-10 rounded-edge border border-ash-300 bg-transparent px-3 text-sm text-ink focus:border-ink focus:outline-none">
            </label>
        </div>
        <label class="flex flex-col gap-1">
            <span class="font-mono text-[0.7rem] uppercase tracking-[0.14em] text-ink-soft">{{ messageLabel }}</span>
            <textarea v-model="message" rows="3" class="rounded-edge border border-ash-300 bg-transparent px-3 py-2 text-sm text-ink focus:border-ink focus:outline-none"></textarea>
        </label>
        <div class="flex items-center gap-3">
            <button type="submit" :disabled="busy" class="h-10 rounded-edge bg-ink px-5 font-mono text-[0.7rem] uppercase tracking-[0.16em] text-alabaster transition-colors hover:bg-obsidian-800 disabled:opacity-60">
                {{ saveLabel }}
            </button>
            <span v-if="saved" class="font-mono text-[0.7rem] uppercase tracking-[0.14em] text-accent">{{ savedLabel }}</span>
            <span v-if="error" role="alert" class="text-sm text-red-700">{{ error }}</span>
        </div>
    </form>
</template>
