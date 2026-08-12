<script setup lang="ts">
import { computed, ref, onMounted, onBeforeUnmount } from "vue";
import events from "MageObsidian_ModernFrontend::js/events";
import Icon from "MageObsidian_ModernFrontend::elements/Icon";
import {
    NOTIFICATION_EVENT,
    LEGACY_TOAST_EVENT,
    NotificationTone,
    type NotificationEvent,
} from "MageObsidian_Storefront::js/notifications";

interface ToastItem extends NotificationEvent {
    id: number;
}

interface Countdown {
    timer: ReturnType<typeof setTimeout> | null;
    remaining: number;
    startedAt: number;
}

const props = withDefaults(
    defineProps<{
        labels?: { dismiss?: string };
    }>(),
    { labels: () => ({}) },
);

const DURATION = 3200;

let nextId = 0;
const toasts = ref<ToastItem[]>([]);
const countdowns = new Map<number, Countdown>();
let unobserve: (() => void) | null = null;

const dismissLabel = computed(() => props.labels?.dismiss ?? "Dismiss");

// Errors need their own assertive region from the start: swapping `aria-live`
// on a region that is already mounted is not reliably announced.
const regions = computed<
    { role: string; live: "polite" | "assertive"; items: ToastItem[] }[]
>(() => [
    {
        role: "alert",
        live: "assertive",
        items: toasts.value.filter((t) => t.tone === NotificationTone.Error),
    },
    {
        role: "status",
        live: "polite",
        items: toasts.value.filter((t) => t.tone !== NotificationTone.Error),
    },
]);

function clearCountdown(id: number): void {
    const countdown = countdowns.get(id);
    if (countdown?.timer) {
        clearTimeout(countdown.timer);
    }
    countdowns.delete(id);
}

function dismiss(id: number): void {
    clearCountdown(id);
    toasts.value = toasts.value.filter((t) => t.id !== id);
}

function show({ message, tone = NotificationTone.Success }: Partial<NotificationEvent>): void {
    if (!message) {
        return;
    }
    const id = ++nextId;
    toasts.value = [...toasts.value, { id, message, tone }];
    countdowns.set(id, {
        timer: setTimeout(() => dismiss(id), DURATION),
        remaining: DURATION,
        startedAt: Date.now(),
    });
}

// Closing releases the whole stack: the row goes away under the pointer, so its
// `mouseleave` may never fire and the survivors would hang paused forever.
function close(id: number): void {
    dismiss(id);
    resume();
}

function pause(): void {
    countdowns.forEach((countdown) => {
        if (!countdown.timer) {
            return;
        }
        clearTimeout(countdown.timer);
        countdown.timer = null;
        countdown.remaining = Math.max(0, countdown.remaining - (Date.now() - countdown.startedAt));
    });
}

function resume(): void {
    countdowns.forEach((countdown, id) => {
        if (countdown.timer) {
            return;
        }
        countdown.startedAt = Date.now();
        countdown.timer = setTimeout(() => dismiss(id), countdown.remaining);
    });
}

const onLegacyToast = (event: Event): void => show((event as CustomEvent).detail ?? {});

onMounted(() => {
    unobserve = events.observe(NOTIFICATION_EVENT, show);
    window.addEventListener(LEGACY_TOAST_EVENT, onLegacyToast);
});

onBeforeUnmount(() => {
    unobserve?.();
    window.removeEventListener(LEGACY_TOAST_EVENT, onLegacyToast);
    Array.from(countdowns.keys()).forEach(clearCountdown);
});
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 print:hidden">
        <div
            v-for="region in regions"
            :key="region.role"
            :role="region.role"
            :aria-live="region.live"
            aria-atomic="true"
            class="relative flex w-full flex-col items-center gap-2"
        >
            <TransitionGroup name="obsidian-toast">
                <div
                    v-for="toast in region.items"
                    :key="toast.id"
                    class="pointer-events-none flex w-full justify-center"
                >
                    <div
                        class="pointer-events-auto flex items-center gap-3 rounded-edge border py-3 pl-5 pr-3 font-mono text-eyebrow uppercase tracking-eyebrow shadow-xl backdrop-blur-md"
                        @mouseenter="pause"
                        @mouseleave="resume"
                        @focusin="pause"
                        @focusout="resume"
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
                        <button
                            type="button"
                            class="rounded-full p-1 opacity-60 transition-opacity hover:opacity-100 focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-current"
                            :aria-label="dismissLabel"
                            @click="close(toast.id)"
                        >
                            <Icon name="x-mark" set="outline" class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
            </TransitionGroup>
        </div>
    </div>
</template>
