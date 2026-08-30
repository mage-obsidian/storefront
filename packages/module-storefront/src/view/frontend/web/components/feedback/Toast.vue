<script setup lang="ts">
import { computed, ref, onMounted, onBeforeUnmount } from "vue";
import Icon from "MageObsidian_ModernFrontend::elements/Icon";
import {
    LEGACY_TOAST_EVENT,
    NotificationTone,
    onNotification,
    type NotificationEvent,
} from "MageObsidian_Storefront::js/notifications";

interface ToastItem extends NotificationEvent {
    id: number;
    duration: number;
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

const DEFAULT_DURATION = 3200;
const MAX_DURATION = 12000;
const MS_PER_CHARACTER = 90;
const MAX_VISIBLE = 4;

const TONE_STYLES: Record<NotificationTone, { bar: string; ink: string; icon: string }> = {
    [NotificationTone.Success]: {
        bar: "bg-accent",
        ink: "text-accent",
        icon: "check-circle",
    },
    [NotificationTone.Error]: {
        bar: "bg-danger",
        ink: "text-danger",
        icon: "exclamation-circle",
    },
    [NotificationTone.Warning]: {
        bar: "bg-sale",
        ink: "text-sale",
        icon: "exclamation-triangle",
    },
    [NotificationTone.Notice]: {
        bar: "bg-ash-400",
        ink: "text-ash-500",
        icon: "information-circle",
    },
};

let nextId = 0;
const toasts = ref<ToastItem[]>([]);
const paused = ref(false);
const countdowns = new Map<number, Countdown>();
let unobserve: (() => void) | null = null;

const dismissLabel = computed(() => props.labels?.dismiss ?? "Dismiss");

// Errors need their own assertive region from the start: swapping `aria-live`
// on a region that is already mounted is not reliably announced.
const regions = computed<
    { role: string; live: "polite" | "assertive"; items: ToastItem[] }[]
>(() => [
    {
        role: "status",
        live: "polite",
        items: toasts.value.filter((t) => t.tone !== NotificationTone.Error),
    },
    {
        role: "alert",
        live: "assertive",
        items: toasts.value.filter((t) => t.tone === NotificationTone.Error),
    },
]);

function styleFor(toast: ToastItem): { bar: string; ink: string; icon: string } {
    return TONE_STYLES[toast.tone] ?? TONE_STYLES[NotificationTone.Notice];
}

function durationFor(message: string, requested?: number): number {
    if (typeof requested === "number" && requested > 0) {
        return requested;
    }
    return Math.min(MAX_DURATION, Math.max(DEFAULT_DURATION, message.length * MS_PER_CHARACTER));
}

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

function show({
    message,
    tone = NotificationTone.Success,
    html = false,
    durationMs,
}: Partial<NotificationEvent>): void {
    if (!message) {
        return;
    }
    const duration = durationFor(message, durationMs);
    const toast: ToastItem = { id: ++nextId, message, tone, html, duration };
    const next = [...toasts.value, toast];
    while (next.length > MAX_VISIBLE) {
        const dropped = next.shift();
        if (dropped) {
            clearCountdown(dropped.id);
        }
    }
    toasts.value = next;

    countdowns.set(toast.id, {
        timer: setTimeout(() => dismiss(toast.id), duration),
        remaining: duration,
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
    paused.value = true;
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
    paused.value = false;
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
    unobserve = onNotification(show);
    window.addEventListener(LEGACY_TOAST_EVENT, onLegacyToast);
});

onBeforeUnmount(() => {
    unobserve?.();
    window.removeEventListener(LEGACY_TOAST_EVENT, onLegacyToast);
    Array.from(countdowns.keys()).forEach(clearCountdown);
});
</script>

<template>
    <div class="toast-host pointer-events-none fixed inset-x-0 bottom-0 flex flex-col items-center gap-2 px-4 sm:items-end print:hidden">
        <div
            v-for="region in regions"
            :key="region.role"
            :role="region.role"
            :aria-live="region.live"
            aria-atomic="true"
            class="relative flex w-full flex-col items-center gap-2 sm:items-end"
        >
            <TransitionGroup name="obsidian-toast">
                <div
                    v-for="toast in region.items"
                    :key="toast.id"
                    class="pointer-events-none flex w-full justify-center sm:justify-end"
                >
                    <div
                        class="obsidian-toast pointer-events-auto relative flex w-full gap-3 overflow-hidden rounded-edge border border-ash-200 bg-alabaster-raised/95 py-3 pl-4 pr-2 text-ink shadow-xl backdrop-blur-md sm:w-96"
                        :class="{ 'obsidian-toast--paused': paused }"
                        :style="{ '--obsidian-toast-duration': `${toast.duration}ms` }"
                        @mouseenter="pause"
                        @mouseleave="resume"
                        @focusin="pause"
                        @focusout="resume"
                    >
                        <span
                            class="absolute inset-y-0 left-0 w-0.5"
                            :class="styleFor(toast).bar"
                            aria-hidden="true"
                        ></span>
                        <Icon
                            :name="styleFor(toast).icon"
                            set="solid"
                            :size="20"
                            class="mt-px h-5 w-5 shrink-0"
                            :class="styleFor(toast).ink"
                        />
                        <div
                            class="min-w-0 flex-1 text-sm leading-snug [&_a]:font-medium [&_a]:underline [&_a]:underline-offset-2"
                        >
                            <span v-if="toast.html" v-html="toast.message"></span>
                            <span v-else>{{ toast.message }}</span>
                        </div>
                        <button
                            type="button"
                            class="h-6 w-6 shrink-0 self-start rounded-full text-ash-500 transition-colors hover:text-ink focus-visible:text-ink focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-current"
                            :aria-label="dismissLabel"
                            @click="close(toast.id)"
                        >
                            <Icon name="x-mark" set="outline" class="mx-auto h-3.5 w-3.5" />
                        </button>
                        <span
                            class="obsidian-toast__countdown absolute inset-x-0 bottom-0 h-0.5 origin-left"
                            aria-hidden="true"
                        ></span>
                    </div>
                </div>
            </TransitionGroup>
        </div>
    </div>
</template>
