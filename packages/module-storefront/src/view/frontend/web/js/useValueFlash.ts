import { ref, watch, onBeforeUnmount, type Ref } from 'vue';

export const FLASH_DURATION = 600;

export function useValueFlash(source: () => unknown, duration = FLASH_DURATION): Ref<boolean> {
    const flashing = ref(false);
    let timer: ReturnType<typeof setTimeout> | undefined;

    watch(source, (next, previous) => {
        if (next === previous) {
            return;
        }
        flashing.value = true;
        clearTimeout(timer);
        timer = setTimeout(() => {
            flashing.value = false;
        }, duration);
    });

    onBeforeUnmount(() => clearTimeout(timer));

    return flashing;
}

export default useValueFlash;
