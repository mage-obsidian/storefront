<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from "vue";
import events from "MageObsidian_ModernFrontend::js/events";
import {
    NOTIFICATION_EVENT,
    LEGACY_TOAST_EVENT,
    NotificationTone,
    type NotificationEvent,
} from "MageObsidian_Storefront::js/notifications";

interface ToastItem extends NotificationEvent {
    id: number;
}

const DURATION = 3200;

let nextId = 0;
const toasts = ref<ToastItem[]>([]);
let unobserve: (() => void) | null = null;

function dismiss(id: number): void {
    toasts.value = toasts.value.filter((t) => t.id !== id);
}

function show({ message, tone = NotificationTone.Success }: Partial<NotificationEvent>): void {
    if (!message) {
        return;
    }
    const id = ++nextId;
    toasts.value = [...toasts.value, { id, message, tone }];
    setTimeout(() => dismiss(id), DURATION);
}

const onLegacyToast = (event: Event): void => show((event as CustomEvent).detail ?? {});

onMounted(() => {
    unobserve = events.observe(NOTIFICATION_EVENT, show);
    window.addEventListener(LEGACY_TOAST_EVENT, onLegacyToast);
});

onBeforeUnmount(() => {
    unobserve?.();
    window.removeEventListener(LEGACY_TOAST_EVENT, onLegacyToast);
});
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 print:hidden">
        <div
            role="status"
            aria-live="polite"
            aria-atomic="true"
            class="flex flex-col items-center gap-2"
        >
            <TransitionGroup name="obsidian-toast">
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="pointer-events-auto flex items-center gap-3 rounded-edge border px-5 py-3 font-mono text-[0.72rem] uppercase tracking-[0.14em] shadow-xl backdrop-blur-md"
                    :class="toast.tone === NotificationTone.Success
                        ? 'border-ash-200 bg-obsidian-950/95 text-on-obsidian'
                        : 'border-sale/40 bg-alabaster/95 text-sale'"
                >
                    <span
                        class="h-1.5 w-1.5 rounded-full"
                        :class="toast.tone === NotificationTone.Success ? 'bg-accent' : 'bg-sale'"
                        aria-hidden="true"
                    ></span>
                    {{ toast.message }}
                </div>
            </TransitionGroup>
        </div>
    </div>
</template>
