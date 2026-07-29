<script setup lang="ts">
import { computed, useId } from "vue";

// Vue half of the shared field. The Twig macro (Magento_Theme::macros/form.twig)
// emits byte-identical markup; both read the same `.field*` styles. Keep the two
// in step — a server-rendered form and a hydrating island have to agree.
export interface FieldOption {
    value: string;
    label: string;
}

const props = withDefaults(
    defineProps<{
        label?: string;
        name?: string;
        id?: string;
        type?: string;
        required?: boolean;
        autocomplete?: string;
        hint?: string;
        error?: string;
        options?: FieldOption[];
    }>(),
    {
        label: "",
        name: "",
        id: "",
        type: "text",
        required: false,
        autocomplete: undefined,
        hint: "",
        error: "",
        options: () => [],
    },
);

// `blur` does not bubble, so a listener that falls through onto the wrapper
// would never fire; the control forwards it explicitly.
const emit = defineEmits<{ blur: [FocusEvent] }>();

const model = defineModel<string>({ default: "" });

const generated = useId();
const fieldId = computed(() => props.id || `field-${generated}`);
const errorId = computed(() => `${fieldId.value}-error`);
const hintId = computed(() => `${fieldId.value}-hint`);
const describedBy = computed(() =>
    [props.hint ? hintId.value : null, errorId.value].filter(Boolean).join(" "),
);
</script>

<template>
    <div class="field">
        <label class="field__label" :for="fieldId">
            {{ label }}<span v-if="required" class="field__required" aria-hidden="true">*</span>
        </label>

        <textarea
            v-if="type === 'textarea'"
            :id="fieldId"
            v-model="model"
            class="field__control"
            :name="name"
            :required="required"
            :aria-required="required ? 'true' : undefined"
            :autocomplete="autocomplete"
            :aria-describedby="describedBy"
            :aria-invalid="error ? 'true' : undefined"
            @blur="emit('blur', $event)"
        />

        <select
            v-else-if="type === 'select'"
            :id="fieldId"
            v-model="model"
            class="field__control"
            :name="name"
            :required="required"
            :aria-required="required ? 'true' : undefined"
            :autocomplete="autocomplete"
            :aria-describedby="describedBy"
            :aria-invalid="error ? 'true' : undefined"
            @blur="emit('blur', $event)"
        >
            <option v-for="option in options" :key="option.value" :value="option.value">
                {{ option.label }}
            </option>
        </select>

        <input
            v-else
            :id="fieldId"
            v-model="model"
            class="field__control"
            :type="type"
            :name="name"
            :required="required"
            :aria-required="required ? 'true' : undefined"
            :autocomplete="autocomplete"
            :aria-describedby="describedBy"
            :aria-invalid="error ? 'true' : undefined"
            @blur="emit('blur', $event)"
        >

        <p v-if="hint" :id="hintId" class="field__hint">{{ hint }}</p>

        <p :id="errorId" class="field__error" role="alert">{{ error }}</p>
    </div>
</template>
