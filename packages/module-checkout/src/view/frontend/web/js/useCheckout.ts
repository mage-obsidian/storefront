/**
 * `useCheckout` — the checkout island's central state (a Pinia store so the step
 * components share one source of truth). It is seeded once from the server-primed
 * `CheckoutConfig` via `init()`, so the first paint (order summary + identity)
 * needs ZERO REST round-trips; the REST mutations (totals/shipping/payment) layer
 * on in later steps via `useCheckoutApi`.
 */
import { ref, computed } from 'vue';
import { defineStore } from 'pinia';
import { ensureSharedPinia } from 'MageObsidian_ModernFrontend::js/store';

export interface CheckoutItem {
    id: number | string;
    name?: string;
    image?: string;
    qty?: number | string;
    rowTotal?: string;
}

// The fixed step order of the one-page flow.
export const STEPS = ['identification', 'shipping', 'payment', 'review'];

ensureSharedPinia();

export const useCheckout = defineStore('mageObsidianCheckout', () => {
    const step = ref('identification');
    const isLoggedIn = ref(false);
    const email = ref('');
    const items = ref<CheckoutItem[]>([]);
    const subtotal = ref('');
    const grandTotal = ref('');
    let seeded = false;

    /**
     * Seed the store from the server-primed config. Idempotent: the island mounts
     * once, but guarding keeps re-mounts (HMR) from clobbering live state.
     */
    function init(config: Record<string, any>): void {
        if (seeded) {
            return;
        }
        seeded = true;
        const cfg = config || {};
        const quote = cfg.quote || {};
        isLoggedIn.value = !!cfg.isLoggedIn;
        email.value = cfg.customerEmail || '';
        items.value = Array.isArray(quote.items) ? quote.items : [];
        subtotal.value = quote.subtotal || '';
        grandTotal.value = quote.grandTotal || '';
        // Skip the identity step entirely for known customers.
        if (isLoggedIn.value && email.value) {
            step.value = 'shipping';
        }
    }

    /** Move to a known step. */
    function goToStep(key: string): void {
        if (STEPS.includes(key)) {
            step.value = key;
        }
    }

    const stepIndex = computed(() => STEPS.indexOf(step.value));
    const itemCount = computed(() => items.value.length);

    return {
        step,
        stepIndex,
        isLoggedIn,
        email,
        items,
        itemCount,
        subtotal,
        grandTotal,
        init,
        goToStep,
    };
});

export default useCheckout;
