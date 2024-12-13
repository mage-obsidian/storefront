<template>
  <div class="relative text-sm bg-surface px-2 py-1 rounded-lg" ref="dropdown">
    <div @click="handleToggle" class="cursor-pointer flex items-center justify-between">
      <slot name="trigger"></slot>
      <template v-if="showArrow">
        <ChevronDownIcon
            :class="[
            'w-4 h-4 ml-2 text-gray-500 transform transition-transform duration-200',
            { 'rotate-180': computedIsOpen }
          ]"
        />
      </template>
    </div>
    <div
        v-if="computedIsOpen"
        id="dropdown"
        class="absolute min-w-full mt-1 right-0 bg-white border border-gray-300 rounded-lg shadow-lg z-10 [&>*]:text-nowrap [&>*]:px-4 [&>*]:py-2"
    >
      <slot name="content"></slot>
    </div>
  </div>
</template>

<script>
import {computed, ref, onMounted, onBeforeUnmount} from "vue";
import {ChevronDownIcon} from "@heroicons/vue/24/solid";

export default {
  name: "SlotDropdown",
  props: {
    modelValue: {
      type: Boolean,
      default: null,
    },
    showArrow: {
      type: Boolean,
      default: true,
    },
    customClass: {
      type: String,
      default: "",
    },
  },
  emits: ["update:modelValue", "open", "close"],
  components: {
    ChevronDownIcon,
  },
  setup(props, {emit}) {
    const isOpen = ref(false);
    const dropdown = ref(null);

    const computedIsOpen = computed(() => {
      return props.modelValue !== null ? props.modelValue : isOpen.value;
    });

    const handleToggle = () => {
      if (props.modelValue !== null) {
        const newValue = !props.modelValue;
        emit("update:modelValue", newValue);
        emit(newValue ? "open" : "close");
      } else {
        isOpen.value = !isOpen.value;
        emit(isOpen.value ? "open" : "close");
      }
    };

    const handleOutsideClick = (event) => {
      if (dropdown.value && !dropdown.value.contains(event.target)) {
        if (props.modelValue !== null) {
          emit("update:modelValue", false);
          emit("close");
        } else {
          isOpen.value = false;
          emit("close");
        }
      }
    };

    onMounted(() => {
      document.addEventListener("click", handleOutsideClick);
    });

    onBeforeUnmount(() => {
      document.removeEventListener("click", handleOutsideClick);
    });

    return {
      customClass: props.customClass,
      dropdown,
      computedIsOpen,
      handleToggle,
    };
  },
};
</script>
