<script setup lang="ts">
import { ref } from "vue";
import GiftMessageForm from "MageObsidian_GiftMessage::components/gift/GiftMessageForm";
import { useGiftMessage, type GiftMessageData, type GiftMessageEndpoint } from "MageObsidian_GiftMessage::js/useGiftMessage";

interface GiftItem {
    id: number | string;
    name: string;
    message: GiftMessageData;
}

const props = defineProps<{
    orderAllowed: boolean;
    itemsAllowed: boolean;
    endpoint: GiftMessageEndpoint;
    orderMessage: GiftMessageData;
    items: GiftItem[];
    panelLabel: string;
    orderTitle: string;
    itemsTitle: string;
    fromLabel: string;
    toLabel: string;
    messageLabel: string;
    saveLabel: string;
    savedLabel: string;
    failLabel: string;
}>();

const api = useGiftMessage(props.endpoint);

const orderForm = ref<InstanceType<typeof GiftMessageForm> | null>(null);
const itemForms = ref<Record<string, InstanceType<typeof GiftMessageForm>>>({});

function setItemForm(id: number | string, el: unknown): void {
    if (el) {
        itemForms.value[String(id)] = el as InstanceType<typeof GiftMessageForm>;
    }
}

async function saveOrder(data: GiftMessageData): Promise<void> {
    try {
        await api.saveOrderMessage(data);
        orderForm.value?.resolve(true);
    } catch (e) {
        orderForm.value?.resolve(false, (e as Error).message || props.failLabel);
    }
}

async function saveItem(id: number | string, data: GiftMessageData): Promise<void> {
    const form = itemForms.value[String(id)];
    try {
        await api.saveItemMessage(id, data);
        form?.resolve(true);
    } catch (e) {
        form?.resolve(false, (e as Error).message || props.failLabel);
    }
}
</script>

<template>
    <details class="mt-10 rounded-edge border border-ash-200 bg-alabaster-raised">
        <summary class="cursor-pointer list-none px-6 py-4 font-mono text-xs uppercase tracking-[0.18em] text-ink [&::-webkit-details-marker]:hidden">
            {{ panelLabel }}
        </summary>
        <div class="flex flex-col gap-8 border-t border-ash-200 px-6 py-6">
            <GiftMessageForm
                v-if="orderAllowed"
                ref="orderForm"
                :title="orderTitle"
                :sender="orderMessage.sender"
                :recipient="orderMessage.recipient"
                :message="orderMessage.message"
                :from-label="fromLabel"
                :to-label="toLabel"
                :message-label="messageLabel"
                :save-label="saveLabel"
                :saved-label="savedLabel"
                @save="saveOrder"
            />

            <div v-if="itemsAllowed && items.length" class="flex flex-col gap-6">
                <p class="font-mono text-xs uppercase tracking-[0.16em] text-ink-soft">{{ itemsTitle }}</p>
                <GiftMessageForm
                    v-for="item in items"
                    :key="item.id"
                    :ref="(el) => setItemForm(item.id, el)"
                    :title="item.name"
                    :sender="item.message.sender"
                    :recipient="item.message.recipient"
                    :message="item.message.message"
                    :from-label="fromLabel"
                    :to-label="toLabel"
                    :message-label="messageLabel"
                    :save-label="saveLabel"
                    :saved-label="savedLabel"
                    @save="(data) => saveItem(item.id, data)"
                />
            </div>
        </div>
    </details>
</template>
