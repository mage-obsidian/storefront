<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from "vue";

// Global toast host (mounted once). Listens for `obsidian:toast` window events —
// dispatched by cart-actions and reusable by wishlist/compare later — and shows
// transient, accessible notifications. The container is an aria-live region so
// screen readers announce each message; success uses polite, errors assertive.
interface ToastItem {
    id: number;
    message: string;
    tone: string;
}

const DURATION = 3200;

let nextId = 0;
const toasts = ref<ToastItem[]>([]);

function dismiss(id: number): void {
    toasts.value = toasts.value.filter((t) => t.id !== id);
}

function onToast(event: Event): void {
    const { message, tone = "success" } = (event as CustomEvent).detail ?? {};
    if (!message) {
        return;
    }
    const id = ++nextId;
    toasts.value = [...toasts.value, { id, message, tone }];
    setTimeout(() => dismiss(id), DURATION);
}

onMounted(() => window.addEventListener("obsidian:toast", onToast));
onBeforeUnmount(() => window.removeEventListener("obsidian:toast", onToast));
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
                    :class="toast.tone === 'error'
                        ? 'border-sale/40 bg-alabaster/95 text-sale'
                        : 'border-ash-200 bg-obsidian-950/95 text-on-obsidian'"
                >
                    <span class="h-1.5 w-1.5 rounded-full" :class="toast.tone === 'error' ? 'bg-sale' : 'bg-accent'" aria-hidden="true"></span>
                    {{ toast.message }}
                </div>
            </TransitionGroup>
        </div>
    </div>
</template>
