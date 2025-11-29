<script setup lang="ts">
import { ref, watch, nextTick, onBeforeUnmount, useId } from "vue";

// Shared off-canvas drawer. Presentation-agnostic: the mobile menu fills it now,
// the mini-cart will reuse it in the cart wave. Owns the dialog a11y semantics,
// close affordances (Escape, backdrop), body scroll-lock, and a focus trap (Tab
// cycles within the dialog); the parent owns the `open` state and reacts to
// `close`. Exposes an `id` so a trigger can wire `aria-controls` to it.
const props = withDefaults(
    defineProps<{
        open?: boolean;
        side?: string;
        label?: string;
        id?: string;
    }>(),
    {
        open: false,
        side: "right",
        label: "",
        id: () => `drawer-${useId()}`,
    },
);
const emit = defineEmits<{ close: [] }>();

const panel = ref<HTMLElement | null>(null);
let previouslyFocused: HTMLElement | null = null;

const FOCUSABLE = [
    "a[href]",
    "button:not([disabled])",
    "input:not([disabled])",
    "textarea:not([disabled])",
    "select:not([disabled])",
    '[tabindex]:not([tabindex="-1"])',
].join(",");

const focusables = (): HTMLElement[] =>
    panel.value ? [...panel.value.querySelectorAll<HTMLElement>(FOCUSABLE)] : [];

const requestClose = (): void => emit("close");

const onKeydown = (event: KeyboardEvent): void => {
    if (event.key === "Escape") {
        requestClose();
        return;
    }
    if (event.key !== "Tab") {
        return;
    }
    // Focus trap: keep Tab/Shift+Tab inside the dialog.
    const items = focusables();
    if (items.length === 0) {
        event.preventDefault();
        panel.value?.focus();
        return;
    }
    const first = items[0];
    const last = items[items.length - 1];
    const active = document.activeElement;
    const inside = panel.value?.contains(active);
    if (event.shiftKey && (active === first || !inside)) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && (active === last || !inside)) {
        event.preventDefault();
        first.focus();
    }
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            previouslyFocused = document.activeElement as HTMLElement | null;
            document.body.style.overflow = "hidden";
            document.addEventListener("keydown", onKeydown);
            nextTick(() => (focusables()[0] ?? panel.value)?.focus());
        } else {
            document.body.style.overflow = "";
            document.removeEventListener("keydown", onKeydown);
            if (previouslyFocused && typeof previouslyFocused.focus === "function") {
                previouslyFocused.focus();
            }
            previouslyFocused = null;
        }
    },
    { immediate: true },
);

// A drawer can be torn down while open (route change, parent unmount); never
// leave the page scroll-locked or a stray key listener behind.
onBeforeUnmount(() => {
    document.body.style.overflow = "";
    document.removeEventListener("keydown", onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition name="obsidian-drawer">
            <div
                v-if="open"
                data-drawer-backdrop
                class="fixed inset-0 z-50 flex bg-obsidian-950/40 backdrop-blur-sm"
                :class="side === 'left' ? 'justify-start' : 'justify-end'"
                @click="requestClose"
            >
                <div
                    :id="id"
                    ref="panel"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="label"
                    tabindex="-1"
                    class="flex h-full w-[88vw] max-w-[380px] flex-col bg-alabaster text-ink shadow-xl outline-none"
                    :class="side === 'left' ? 'border-r border-ash-200' : 'border-l border-ash-200'"
                    @click.stop
                >
                    <slot />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
