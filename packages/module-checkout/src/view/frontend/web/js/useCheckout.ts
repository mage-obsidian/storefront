/**
 * `useCheckout` — the checkout island's central state (a Pinia store so the step
 * components share one source of truth). It is seeded once from the server-primed
 * `CheckoutConfig` via `init()`, so the first paint (order summary + identity)
 * needs ZERO REST round-trips; the REST mutations (shipping rates, shipping
 * information, payment) layer on through the actions, which wrap the Vue-free
 * `createCheckoutApi` against Magento's native quote endpoints.
 */
import { ref, computed } from 'vue';
import { defineStore } from 'pinia';
import { ensureSharedPinia } from 'MageObsidian_ModernFrontend::js/store';
import { createCheckoutApi } from 'MageObsidian_Checkout::js/useCheckoutApi';
import { useCustomerData } from 'MageObsidian_ModernFrontend::js/customer-data';
import { emptyAddress, toRestAddress, type AddressData } from 'MageObsidian_Storefront::js/address';

export interface CheckoutItem {
    id: number | string;
    name?: string;
    image?: string;
    qty?: number | string;
    rowTotal?: string;
}

export interface ShippingMethod {
    carrier_code: string;
    method_code: string;
    carrier_title?: string;
    method_title?: string;
    amount?: number;
    available?: boolean;
    error_message?: string;
}

export interface PaymentMethod {
    code: string;
    title: string;
}

export interface VaultToken {
    publicHash: string;
    methodCode: string;
    last4: string;
    type: string;
    typeLabel: string;
    expiration: string;
}

export interface TotalSegment {
    code: string;
    title: string;
    value: number | null;
}

export interface Agreement {
    agreementId: number | string;
    content: string;
    checkboxText: string;
    mode: number;
    contentHeight?: string;
}

export const AGREEMENT_MODE_MANUAL = 1;

interface QuoteTotals {
    grand_total?: number;
    total_segments?: TotalSegment[];
}

interface ShippingInformationResult {
    payment_methods?: PaymentMethod[];
    totals?: QuoteTotals;
}

// The fixed step order of the one-page flow.
export const STEPS = ['identification', 'shipping', 'payment', 'review'];

ensureSharedPinia();

export const useCheckout = defineStore('mageObsidianCheckout', () => {
    const step = ref('identification');
    // Presentation layout the island renders with: 'stepped' (the wizard) or
    // 'onepage'. Domain logic is layout-agnostic; only the components read this.
    const layout = ref('stepped');
    const isLoggedIn = ref(false);
    const email = ref('');
    const items = ref<CheckoutItem[]>([]);
    const subtotal = ref('');
    const grandTotal = ref('');
    const currencyFormat = ref('');

    const shippingAddress = ref<AddressData>(emptyAddress());
    const shippingMethods = ref<ShippingMethod[]>([]);
    const selectedMethod = ref<ShippingMethod | null>(null);
    const paymentMethods = ref<PaymentMethod[]>([]);
    const vaultTokens = ref<VaultToken[]>([]);
    const selectedTokenHash = ref('');
    const loadingRates = ref(false);
    const savingShipping = ref(false);
    const error = ref('');

    const billingAddress = ref<AddressData>(emptyAddress());
    const sameAsShipping = ref(true);
    const selectedPayment = ref('');
    const totalSegments = ref<TotalSegment[]>([]);
    const couponCode = ref('');
    const appliedCoupon = ref('');
    const couponError = ref('');
    const placingOrder = ref(false);
    const orderError = ref('');
    const successUrl = ref('');

    const guestCheckout = ref(true);
    const guestCheckoutLogin = ref(false);
    const displayBillingOnPayment = ref(true);
    const maxSummaryItems = ref(10);
    const agreementsEnabled = ref(false);
    const agreements = ref<Agreement[]>([]);
    const acceptedAgreements = ref<Array<number | string>>([]);

    let seeded = false;
    let api: ReturnType<typeof createCheckoutApi> | null = null;

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
        layout.value = cfg.layoutMode === 'onepage' ? 'onepage' : 'stepped';
        isLoggedIn.value = !!cfg.isLoggedIn;
        email.value = cfg.customerEmail || '';
        items.value = Array.isArray(quote.items) ? quote.items : [];
        subtotal.value = quote.subtotal || '';
        grandTotal.value = quote.grandTotal || '';
        currencyFormat.value = cfg.currencyFormat || '';
        successUrl.value = cfg.successUrl || '';
        shippingAddress.value = emptyAddress(cfg.defaultCountry || '');
        billingAddress.value = emptyAddress(cfg.defaultCountry || '');
        vaultTokens.value = Array.isArray(cfg.vault) ? (cfg.vault as VaultToken[]) : [];
        guestCheckout.value = cfg.guestCheckout !== false;
        guestCheckoutLogin.value = !!cfg.guestCheckoutLogin;
        displayBillingOnPayment.value = cfg.displayBillingOnPayment !== false;
        if (typeof cfg.maxSummaryItems === 'number' && cfg.maxSummaryItems > 0) {
            maxSummaryItems.value = cfg.maxSummaryItems;
        }
        const agreementsCfg = cfg.agreements || {};
        agreementsEnabled.value = !!agreementsCfg.enabled;
        agreements.value = Array.isArray(agreementsCfg.items) ? (agreementsCfg.items as Agreement[]) : [];
        api = createCheckoutApi({
            restBaseUrl: cfg.restBaseUrl || '',
            isLoggedIn: isLoggedIn.value,
            maskedCartId: cfg.maskedCartId || '',
        });
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

    /** Record the guest email and advance to the shipping step. */
    function setEmail(value: string): void {
        email.value = value;
        goToStep('shipping');
    }

    /**
     * Whether the email is free of an existing account (native
     * `customers/isEmailAvailable`). A check failure never blocks the guest, so
     * it degrades to "available".
     */
    async function checkEmailAvailable(value: string): Promise<boolean> {
        if (!api || !guestCheckoutLogin.value) {
            return true;
        }
        try {
            return (await api.isEmailAvailable(value)) !== false;
        } catch {
            return true;
        }
    }

    /**
     * Fetch the shipping rates for the current address from the native
     * estimate-shipping-methods endpoint, keeping only the available ones and
     * pre-selecting the first when nothing is chosen yet.
     */
    async function estimateShipping(): Promise<boolean> {
        if (!api) {
            return false;
        }
        loadingRates.value = true;
        error.value = '';
        try {
            const rest = toRestAddress(shippingAddress.value);
            const rates = (await api.estimateShippingMethods(rest)) as ShippingMethod[];
            shippingMethods.value = (Array.isArray(rates) ? rates : []).filter((r) => r.available !== false);
            if (shippingMethods.value.length > 0 && !methodInList(selectedMethod.value)) {
                selectedMethod.value = shippingMethods.value[0];
            }
            return true;
        } catch (e) {
            error.value = e instanceof Error ? e.message : String(e);
            shippingMethods.value = [];
            return false;
        } finally {
            loadingRates.value = false;
        }
    }

    /** Choose a shipping method by carrier+method code. */
    function selectMethod(method: ShippingMethod): void {
        selectedMethod.value = method;
    }

    /**
     * Persist the shipping address + method via the native shipping-information
     * endpoint. On success it stores the returned payment methods + totals (for
     * the payment step) and advances. The guest billing address mirrors shipping
     * until the payment step lets the shopper change it.
     */
    async function saveShipping(): Promise<boolean> {
        if (!api || !selectedMethod.value) {
            return false;
        }
        savingShipping.value = true;
        error.value = '';
        try {
            const address = toRestAddress(shippingAddress.value, isLoggedIn.value ? {} : { email: email.value });
            const result = (await api.setShippingInformation({
                shipping_address: address,
                billing_address: address,
                shipping_carrier_code: selectedMethod.value.carrier_code,
                shipping_method_code: selectedMethod.value.method_code,
            })) as ShippingInformationResult;
            paymentMethods.value = result.payment_methods ?? [];
            if (paymentMethods.value.length > 0 && !selectedPayment.value) {
                selectedPayment.value = paymentMethods.value[0].code;
            }
            captureTotals(result.totals);
            goToStep('payment');
            return true;
        } catch (e) {
            error.value = e instanceof Error ? e.message : String(e);
            return false;
        } finally {
            savingShipping.value = false;
        }
    }

    function selectPayment(code: string): void {
        selectedPayment.value = code;
        selectedTokenHash.value = '';
    }

    /**
     * Pick a saved card. Selecting one routes payment through its vault method and
     * carries the token's public hash into the order; passing '' clears it back to
     * the plain method selection.
     */
    function selectVaultToken(publicHash: string): void {
        const token = vaultTokens.value.find((t) => t.publicHash === publicHash);
        selectedTokenHash.value = token ? publicHash : '';
        if (token) {
            selectedPayment.value = token.methodCode;
        }
    }

    function toggleAgreement(agreementId: number | string): void {
        const index = acceptedAgreements.value.indexOf(agreementId);
        if (index === -1) {
            acceptedAgreements.value = [...acceptedAgreements.value, agreementId];
        } else {
            acceptedAgreements.value = acceptedAgreements.value.filter((id) => id !== agreementId);
        }
    }

    async function applyCoupon(code: string): Promise<boolean> {
        if (!api || code.trim() === '') {
            return false;
        }
        couponError.value = '';
        try {
            await api.applyCoupon(code.trim());
            appliedCoupon.value = code.trim();
            couponCode.value = '';
            await refreshTotals();
            return true;
        } catch (e) {
            couponError.value = e instanceof Error ? e.message : String(e);
            return false;
        }
    }

    async function removeCoupon(): Promise<boolean> {
        if (!api) {
            return false;
        }
        couponError.value = '';
        try {
            await api.removeCoupon();
            appliedCoupon.value = '';
            await refreshTotals();
            return true;
        } catch (e) {
            couponError.value = e instanceof Error ? e.message : String(e);
            return false;
        }
    }

    async function refreshTotals(): Promise<void> {
        if (!api) {
            return;
        }
        try {
            captureTotals((await api.getTotals()) as QuoteTotals);
        } catch {
            // Keep the last known totals on a transient failure.
        }
    }

    async function placeOrder(): Promise<number | null> {
        if (!api || selectedPayment.value === '' || !allRequiredAccepted.value) {
            return null;
        }
        placingOrder.value = true;
        orderError.value = '';
        try {
            const source = sameAsShipping.value ? shippingAddress.value : billingAddress.value;
            const billing = toRestAddress(source, isLoggedIn.value ? {} : { email: email.value });
            const token = vaultTokens.value.find((t) => t.publicHash === selectedTokenHash.value);
            const paymentMethod: Record<string, unknown> = token
                ? { method: token.methodCode, additional_data: { public_hash: token.publicHash } }
                : { method: selectedPayment.value };
            if (agreementsEnabled.value && acceptedAgreements.value.length > 0) {
                paymentMethod.extension_attributes = {
                    agreement_ids: acceptedAgreements.value.map((id) => String(id)),
                };
            }
            const payload: Record<string, unknown> = {
                paymentMethod,
                billingAddress: billing,
            };
            if (!isLoggedIn.value) {
                payload.email = email.value;
            }
            const orderId = (await api.placeOrder(payload)) as number;
            // REST place-order does not bump the section version cookie, so the
            // cart badge would stay stale; force a refresh before leaving.
            try {
                await useCustomerData().reload(['cart'], { force: true });
            } catch {
                // Non-fatal: the cart page would still reconcile on next load.
            }
            if (successUrl.value !== '') {
                window.location.assign(successUrl.value);
            }
            return orderId;
        } catch (e) {
            orderError.value = e instanceof Error ? e.message : String(e);
            return null;
        } finally {
            placingOrder.value = false;
        }
    }

    function captureTotals(totals?: QuoteTotals): void {
        if (!totals) {
            return;
        }
        totalSegments.value = Array.isArray(totals.total_segments) ? totals.total_segments : [];
        if (typeof totals.grand_total === 'number') {
            grandTotal.value = formatTotal(totals.grand_total);
        }
    }

    function formatTotal(amount: number): string {
        return (currencyFormat.value || '%s').replace('%s', amount.toFixed(2));
    }

    function methodInList(method: ShippingMethod | null): boolean {
        return (
            method !== null &&
            shippingMethods.value.some(
                (r) => r.carrier_code === method.carrier_code && r.method_code === method.method_code,
            )
        );
    }

    const stepIndex = computed(() => STEPS.indexOf(step.value));
    const itemCount = computed(() => items.value.length);
    const selectedMethodKey = computed(() =>
        selectedMethod.value ? `${selectedMethod.value.carrier_code}_${selectedMethod.value.method_code}` : '',
    );
    const requiredAgreementIds = computed(() =>
        agreements.value.filter((a) => a.mode === AGREEMENT_MODE_MANUAL).map((a) => a.agreementId),
    );
    const allRequiredAccepted = computed(() =>
        !agreementsEnabled.value || requiredAgreementIds.value.every((id) => acceptedAgreements.value.includes(id)),
    );
    const visibleItems = computed(() => items.value.slice(0, maxSummaryItems.value));
    const hiddenItemCount = computed(() => Math.max(0, items.value.length - maxSummaryItems.value));

    return {
        step,
        layout,
        stepIndex,
        isLoggedIn,
        email,
        items,
        itemCount,
        subtotal,
        grandTotal,
        currencyFormat,
        shippingAddress,
        shippingMethods,
        selectedMethod,
        selectedMethodKey,
        paymentMethods,
        vaultTokens,
        selectedTokenHash,
        loadingRates,
        savingShipping,
        error,
        billingAddress,
        sameAsShipping,
        selectedPayment,
        totalSegments,
        couponCode,
        appliedCoupon,
        couponError,
        placingOrder,
        orderError,
        guestCheckout,
        guestCheckoutLogin,
        displayBillingOnPayment,
        maxSummaryItems,
        agreementsEnabled,
        agreements,
        acceptedAgreements,
        requiredAgreementIds,
        allRequiredAccepted,
        visibleItems,
        hiddenItemCount,
        formatTotal,
        init,
        goToStep,
        setEmail,
        checkEmailAvailable,
        toggleAgreement,
        estimateShipping,
        selectMethod,
        saveShipping,
        selectPayment,
        selectVaultToken,
        applyCoupon,
        removeCoupon,
        refreshTotals,
        placeOrder,
    };
});

export default useCheckout;
