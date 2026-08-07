<script setup lang="ts">
import Icon from "MageObsidian_ModernFrontend::elements/Icon";

export interface RailStep {
    key: string;
    label: string;
    index: number;
    done: boolean;
    active: boolean;
    reachable?: boolean;
}

const props = withDefaults(
    defineProps<{
        steps: RailStep[];
        label?: string;
        doneLabel?: string;
        currentLabel?: string;
    }>(),
    { label: "Checkout steps", doneLabel: "completed", currentLabel: "current step" },
);

const emit = defineEmits<{ (event: "go", step: RailStep): void }>();

const state = (step: RailStep): string => {
    if (step.done) {
        return "done";
    }
    return step.active ? "active" : "pending";
};

const hint = (step: RailStep): string | undefined => {
    if (step.done) {
        return props.doneLabel;
    }
    return step.active ? props.currentLabel : undefined;
};
</script>

<template>
    <nav class="step-rail" :aria-label="label">
        <ol class="step-rail__list">
            <li
                v-for="step in steps"
                :key="step.key"
                class="step-rail__item"
                :data-state="state(step)"
                :aria-current="step.active ? 'step' : undefined"
            >
                <button
                    type="button"
                    class="step-rail__button"
                    :disabled="!step.reachable"
                    @click="emit('go', step)"
                >
                    <span class="step-rail__mark" aria-hidden="true">
                        <Icon v-if="step.done" name="check" :size="16" />
                        <template v-else>{{ step.index + 1 }}</template>
                    </span>
                    <span class="step-rail__label">{{ step.label }}</span>
                    <span v-if="hint(step)" class="sr-only">{{ hint(step) }}</span>
                </button>
            </li>
        </ol>
    </nav>
</template>
