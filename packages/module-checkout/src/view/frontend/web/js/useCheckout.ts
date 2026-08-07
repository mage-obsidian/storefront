/**
 * `useCheckout` — the checkout island's central state (a Pinia store so the step
 * components share one source of truth). It is seeded once from the server-primed
 * `CheckoutConfig` via `init()`, so the first paint (order summary + identity)
 * needs ZERO REST round-trips; the REST mutations (shipping rates, shipping
 * information, payment) layer on through the actions, which wrap the Vue-free
 * `createCheckoutApi` against Magento's native quote endpoints.
 */
import { ref, computed, watch } from 'vue';
import { defineStore } from 'pinia';
import { ensureSharedPinia } from 'MageObsidian_ModernFrontend::js/store';
import { createCheckoutApi } from 'MageObsidian_Checkout::js/useCheckoutApi';
import { useCustomerData } from 'MageObsidian_ModernFrontend::js/customer-data';
import { emptyAddress, missingFields, toRestAddress, type AddressData } from 'MageObsidian_Storefront::js/address';
import events from 'MageObsidian_ModernFrontend::js/events';
import { MutationPhase } from 'mage-obsidian/runtime/mutationEvent.ts';
import {
    CHECKOUT_STEP_CHANGE_EVENT,
    CheckoutOperation,
    checkoutEvent,
    type CheckoutEvent,
} from 'MageObsidian_Checkout::js/checkout-events';

export interface CheckoutItemOption {
    label: string;
    value: string;
}

export interface CheckoutItem {
    id: number | string;
    name?: string;
    image?: string;
    qty?: number | string;
    rowTotal?: string;
    options?: CheckoutItemOption[];
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

export interface SavedAddress extends AddressData {
    id: number;
    label: string;
    isDefaultShipping: boolean;
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

export interface QuoteShipping {
    address?: AddressData | null;
    method?: { carrier_code: string; method_code: string };
    email?: string;
}

export const CheckoutStep = {
    Identification: 'identification',
    Shipping: 'shipping',
    Payment: 'payment',
    Review: 'review',
} as const;

export type CheckoutStep = (typeof CheckoutStep)[keyof typeof CheckoutStep];

// The fixed step order of the one-page flow.
export const STEPS: readonly CheckoutStep[] = Object.values(CheckoutStep);

export const CheckoutLayout = {
    Stepped: 'stepped',
    OnePage: 'onepage',
} as const;

export type CheckoutLayout = (typeof CheckoutLayout)[keyof typeof CheckoutLayout];

export const SHIPPING_SYNC_DEBOUNCE_MS = 400;

async function runFlow<Result>(
    operation: CheckoutOperation,
    payload: Record<string, unknown>,
    run: () => Promise<Result>,
    failed: (result: Result) => boolean,
): Promise<Result | null> {
    const request = await events.dispatch(checkoutEvent(operation, MutationPhase.Before), {
        operation,
        cancelled: false,
        payload,
    } satisfies CheckoutEvent);
    if (request.cancelled) {
        return null;
    }

    const result = await run();
    await events.dispatch(checkoutEvent(operation, MutationPhase.After), { ...request, result });
    if (failed(result)) {
        await events.dispatch(checkoutEvent(operation, MutationPhase.Failed), { ...request, result });
    }
    return result;
}

ensureSharedPinia();

export const useCheckout = defineStore('mageObsidianCheckout', () => {
    const step = ref<CheckoutStep>(CheckoutStep.Identification);
    const furthestStepIndex = ref(0);
    // Presentation layout the island renders with: 'stepped' (the wizard) or
    // 'onepage'. Domain logic is layout-agnostic; only the components read this.
    const layout = ref<CheckoutLayout>(CheckoutLayout.Stepped);
    const isLoggedIn = ref(false);
    const email = ref('');
    const items = ref<CheckoutItem[]>([]);
    const subtotal = ref('');
    const grandTotal = ref('');
    const currencyFormat = ref('');

    const statesRequired = ref<string[]>([]);
    const shippingAddress = ref<AddressData>(emptyAddress());
    const savedAddresses = ref<SavedAddress[]>([]);
    const selectedAddressId = ref<number | null>(null);
    const saveAddress = ref(true);
    const shippingMethods = ref<ShippingMethod[]>([]);
    const selectedMethod = ref<ShippingMethod | null>(null);
    const paymentMethods = ref<PaymentMethod[]>([]);
    const vaultTokens = ref<VaultToken[]>([]);
    const selectedTokenHash = ref('');
    const loadingRates = ref(false);
    const ratesRequested = ref(false);
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

    let publicSeeded = false;
    let restBaseUrl = '';
    let defaultCountry = '';
    let api: ReturnType<typeof createCheckoutApi> | null = null;
    const ready = ref(false);

    const persistedSignature = ref('');
    const shippingSyncPending = ref(false);
    const shippingSaved = ref(false);
    const shippingTouched = ref(false);
    let ratedSignature = '';
    let syncTimer: ReturnType<typeof setTimeout> | undefined;
    let syncChain: Promise<void> = Promise.resolve();

    /**
     * Seed the store-scoped half. Leaves the store unready on purpose: nothing
     * here says anything about this shopper's cart. Guarded against HMR re-mounts.
     */
    function initPublic(config: Record<string, any>): void {
        if (publicSeeded) {
            return;
        }
        publicSeeded = true;
        const cfg = config || {};
        layout.value = cfg.layoutMode === 'onepage' ? 'onepage' : 'stepped';
        successUrl.value = cfg.successUrl || '';
        restBaseUrl = cfg.restBaseUrl || '';
        defaultCountry = cfg.defaultCountry || '';
        statesRequired.value = Array.isArray(cfg.statesRequired) ? (cfg.statesRequired as string[]) : [];
        shippingAddress.value = emptyAddress(defaultCountry);
        billingAddress.value = emptyAddress(defaultCountry);
        guestCheckout.value = cfg.guestCheckout !== false;
        guestCheckoutLogin.value = !!cfg.guestCheckoutLogin;
        displayBillingOnPayment.value = cfg.displayBillingOnPayment !== false;
        if (typeof cfg.maxSummaryItems === 'number' && cfg.maxSummaryItems > 0) {
            maxSummaryItems.value = cfg.maxSummaryItems;
        }
        const agreementsCfg = cfg.agreements || {};
        agreementsEnabled.value = !!agreementsCfg.enabled;
        agreements.value = Array.isArray(agreementsCfg.items) ? (agreementsCfg.items as Agreement[]) : [];
    }

    /**
     * Apply the customer/quote-scoped half. Called more than once by design — the
     * section reloads after every cart mutation — so only the first delivery
     * seeds identity; later ones refresh the summary without discarding what the
     * shopper has typed.
     */
    function applyPrivate(data: Record<string, any>): void {
        const d = data || {};
        const quote = d.quote || {};
        items.value = Array.isArray(quote.items) ? quote.items : [];
        subtotal.value = quote.subtotal || '';
        grandTotal.value = quote.grandTotal || '';

        if (ready.value) {
            return;
        }

        isLoggedIn.value = !!d.isLoggedIn;
        email.value = d.customerEmail || email.value;
        currencyFormat.value = d.currencyFormat || '';
        vaultTokens.value = Array.isArray(d.vault) ? (d.vault as VaultToken[]) : [];
        api = createCheckoutApi({
            restBaseUrl,
            isLoggedIn: isLoggedIn.value,
            maskedCartId: d.maskedCartId || '',
        });
        savedAddresses.value = Array.isArray(d.addresses) ? (d.addresses as SavedAddress[]) : [];
        if (!restoreShipping(d.shipping as QuoteShipping | undefined)) {
            const preferred = savedAddresses.value.find((a) => a.isDefaultShipping) ?? savedAddresses.value[0];
            if (preferred) {
                selectAddress(preferred.id);
            }
        }
        ready.value = true;
        if (email.value !== '') {
            enterStep(CheckoutStep.Shipping);
        }
    }

    function restoreShipping(shipping?: QuoteShipping | null): boolean {
        const held = shipping?.address;
        if (!held || (held.countryId ?? '') === '') {
            return false;
        }

        const street = [...(held.street ?? [])];
        while (street.length < 2) {
            street.push('');
        }
        shippingAddress.value = { ...emptyAddress(defaultCountry), ...held, street };
        selectedAddressId.value =
            savedAddresses.value.find((a) => addressKey(a) === addressKey(shippingAddress.value))?.id ?? null;

        const heldEmail = shipping?.email ?? '';
        if (!isLoggedIn.value && heldEmail !== '') {
            email.value = heldEmail;
        }
        const method = shipping?.method;
        if (method && method.carrier_code !== '') {
            selectedMethod.value = { carrier_code: method.carrier_code, method_code: method.method_code };
        }
        return true;
    }

    /** Copies rather than references: AddressForm writes into `street` in place. */
    function selectAddress(id: number | null): void {
        selectedAddressId.value = id;
        const saved = id === null ? undefined : savedAddresses.value.find((a) => a.id === id);
        if (!saved) {
            selectedAddressId.value = null;
            shippingAddress.value = emptyAddress(defaultCountry);
            return;
        }

        const street = [...(saved.street ?? [])];
        while (street.length < 2) {
            street.push('');
        }

        shippingAddress.value = {
            firstname: saved.firstname || '',
            lastname: saved.lastname || '',
            company: saved.company || '',
            street,
            city: saved.city || '',
            region: saved.region || '',
            regionId: saved.regionId ?? null,
            postcode: saved.postcode || '',
            countryId: saved.countryId || defaultCountry,
            telephone: saved.telephone || '',
        };
    }

    /** Seed both halves from one inlined payload (the uncached path). */
    function init(config: Record<string, any>): void {
        initPublic(config);
        applyPrivate(config);
    }

    function clearRates(): void {
        shippingMethods.value = [];
        selectedMethod.value = null;
        paymentMethods.value = [];
        ratesRequested.value = false;
        shippingSaved.value = false;
        ratedSignature = '';
        error.value = '';
    }

    async function shippingSyncPass(withEstimate: boolean): Promise<void> {
        if (!rateReady.value) {
            return;
        }
        const signature = addressKey(shippingAddress.value);
        if (withEstimate && signature !== ratedSignature) {
            if (!(await estimateShipping())) {
                return;
            }
            ratedSignature = signature;
        }
        if (!addressComplete.value || !emailReady.value || selectedMethod.value === null) {
            return;
        }
        if (!shippingUnsaved.value) {
            return;
        }
        await saveShipping();
    }

    function runShippingSync(withEstimate: boolean): Promise<void> {
        const pass = async (): Promise<void> => {
            try {
                await shippingSyncPass(withEstimate);
            } finally {
                if (syncTimer === undefined) {
                    shippingSyncPending.value = false;
                }
            }
        };
        syncChain = syncChain.then(pass, pass);

        return syncChain;
    }

    function scheduleShippingSync(): void {
        clearTimeout(syncTimer);
        shippingSyncPending.value = true;
        syncTimer = setTimeout(() => {
            syncTimer = undefined;
            void runShippingSync(true);
        }, SHIPPING_SYNC_DEBOUNCE_MS);
    }

    function cancelShippingSync(): void {
        clearTimeout(syncTimer);
        syncTimer = undefined;
        shippingSyncPending.value = false;
    }

    async function flushShippingSync(): Promise<boolean> {
        cancelShippingSync();
        await runShippingSync(false);

        return !shippingDirty.value;
    }

    function enterStep(key: CheckoutStep): void {
        step.value = key;
        furthestStepIndex.value = Math.max(furthestStepIndex.value, STEPS.indexOf(key));
    }

    /** Move to a known step. */
    function goToStep(key: string): void {
        if (!STEPS.includes(key as CheckoutStep) || key === step.value) {
            return;
        }
        const from = step.value;
        enterStep(key as CheckoutStep);
        void events.dispatch(CHECKOUT_STEP_CHANGE_EVENT, { from, to: step.value });
    }

    /** Record the guest email and advance to the shipping step. */
    function setEmail(value: string): void {
        email.value = value;
        goToStep(CheckoutStep.Shipping);
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
        return (
            (await runFlow(
                CheckoutOperation.EstimateShipping,
                { address: shippingAddress.value },
                doEstimateShipping,
                (ok) => !ok,
            )) ?? false
        );
    }

    async function doEstimateShipping(): Promise<boolean> {
        loadingRates.value = true;
        ratesRequested.value = true;
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
        return (
            (await runFlow(
                CheckoutOperation.SaveShipping,
                { address: shippingAddress.value, method: selectedMethod.value },
                doSaveShipping,
                (ok) => !ok,
            )) ?? false
        );
    }

    async function doSaveShipping(): Promise<boolean> {
        savingShipping.value = true;
        error.value = '';
        const signature = shippingSignature.value;
        try {
            const extra = isLoggedIn.value ? {} : { email: email.value };
            const result = (await api.setShippingInformation({
                shipping_address: toRestAddress(shippingAddress.value, {
                    ...extra,
                    ...(canSaveAddress.value ? { saveInAddressBook: saveAddress.value } : {}),
                }),
                billing_address: toRestAddress(shippingAddress.value, extra),
                shipping_carrier_code: selectedMethod.value.carrier_code,
                shipping_method_code: selectedMethod.value.method_code,
            })) as ShippingInformationResult;
            paymentMethods.value = result.payment_methods ?? [];
            if (paymentMethods.value.length > 0 && !selectedPayment.value) {
                selectedPayment.value = paymentMethods.value[0].code;
            }
            captureTotals(result.totals);
            persistedSignature.value = signature;
            shippingSaved.value = true;
            shippingTouched.value = true;
            goToStep(CheckoutStep.Payment);
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
        return (
            (await runFlow(
                CheckoutOperation.ApplyCoupon,
                { code: code.trim() },
                () => doApplyCoupon(code),
                (ok) => !ok,
            )) ?? false
        );
    }

    async function doApplyCoupon(code: string): Promise<boolean> {
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
        return (
            (await runFlow(CheckoutOperation.RemoveCoupon, {}, doRemoveCoupon, (ok) => !ok)) ?? false
        );
    }

    async function doRemoveCoupon(): Promise<boolean> {
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
        if (!(await flushShippingSync())) {
            orderError.value = error.value;
            return null;
        }
        return (
            (await runFlow(
                CheckoutOperation.PlaceOrder,
                { payment: selectedPayment.value },
                doPlaceOrder,
                (orderId) => orderId === null,
            )) ?? null
        );
    }

    async function doPlaceOrder(): Promise<number | null> {
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

    function addressKey(address: AddressData): string {
        return JSON.stringify([
            address.firstname.trim(),
            address.lastname.trim(),
            address.company.trim(),
            (address.street ?? []).map((line) => line.trim()).filter((line) => line !== ''),
            address.city.trim(),
            address.region.trim(),
            address.regionId ?? null,
            address.postcode.trim(),
            address.countryId.trim(),
            address.telephone.trim(),
        ]);
    }

    function methodInList(method: ShippingMethod | null): boolean {
        return (
            method !== null &&
            shippingMethods.value.some(
                (r) => r.carrier_code === method.carrier_code && r.method_code === method.method_code,
            )
        );
    }

    const canSaveAddress = computed(() => isLoggedIn.value && selectedAddressId.value === null);
    const regionRequired = computed(() => statesRequired.value.includes(shippingAddress.value.countryId));
    const rateReady = computed(() => {
        const a = shippingAddress.value;
        return (
            a.countryId.trim() !== '' &&
            a.postcode.trim() !== '' &&
            (!regionRequired.value || a.region.trim() !== '' || a.regionId !== null)
        );
    });
    const addressComplete = computed(() => missingFields(shippingAddress.value, regionRequired.value).length === 0);
    const emailReady = computed(() => isLoggedIn.value || email.value.trim() !== '');
    const shippingSignature = computed(() => `${addressKey(shippingAddress.value)}|${selectedMethodKey.value}`);
    const shippingUnsaved = computed(
        () => !shippingSaved.value || shippingSignature.value !== persistedSignature.value,
    );
    const shippingDirty = computed(() => shippingTouched.value && shippingUnsaved.value);
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

    watch(rateReady, (canQuote) => {
        if (!canQuote) {
            clearRates();
        }
    });

    return {
        step,
        layout,
        stepIndex,
        furthestStepIndex,
        isLoggedIn,
        email,
        items,
        itemCount,
        subtotal,
        grandTotal,
        currencyFormat,
        shippingAddress,
        savedAddresses,
        selectedAddressId,
        saveAddress,
        canSaveAddress,
        selectAddress,
        regionRequired,
        rateReady,
        addressComplete,
        emailReady,
        shippingSignature,
        shippingDirty,
        shippingSyncPending,
        shippingMethods,
        selectedMethod,
        selectedMethodKey,
        paymentMethods,
        vaultTokens,
        selectedTokenHash,
        loadingRates,
        ratesRequested,
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
        ready,
        init,
        initPublic,
        applyPrivate,
        goToStep,
        setEmail,
        checkEmailAvailable,
        toggleAgreement,
        estimateShipping,
        selectMethod,
        saveShipping,
        scheduleShippingSync,
        cancelShippingSync,
        flushShippingSync,
        selectPayment,
        selectVaultToken,
        applyCoupon,
        removeCoupon,
        refreshTotals,
        placeOrder,
    };
});

export default useCheckout;
