/**
 * `useInstantPurchase` — Vue-free logic for the PDP instant-purchase button.
 *
 * It reads the native `instant-purchase` customer-data section (token + default
 * addresses + shipping method, populated only when a tokenizing gateway is
 * configured) and, on confirm, POSTs the native place-order controller with those
 * instant-purchase params plus the product-form fields. The product fields are
 * serialized from the PDP simple form (`form.pdp__form`), so simple/simple-with-
 * options work; configurable/bundle (island-only, no plain form) submit without
 * their selected options and are left unverified — there is no tokenizing gateway
 * in this environment to exercise the charge path.
 */
import { computed, type ComputedRef } from 'vue';
import { useCustomerData } from 'MageObsidian_ModernFrontend::js/customer-data';

export interface InstantPurchaseSection {
    available?: boolean;
    paymentToken?: { publicHash: string; summary: string };
    shippingAddress?: { id: number; summary: string };
    billingAddress?: { id: number; summary: string };
    shippingMethod?: { carrier: string; method: string; summary: string };
}

export interface PlaceOrderResult {
    ok: boolean;
    message: string;
}

interface SectionStore {
    section(name: string): unknown;
    reload(names?: string[], options?: { force?: boolean }): Promise<void>;
}

export interface InstantPurchaseController {
    data: ComputedRef<InstantPurchaseSection>;
    available: ComputedRef<boolean>;
    placeOrder(): Promise<PlaceOrderResult>;
}

export function createInstantPurchase(
    purchaseUrl: string,
    doc: Document = document,
    store: SectionStore = useCustomerData() as unknown as SectionStore,
): InstantPurchaseController {
    const data = computed<InstantPurchaseSection>(
        () => (store.section('instant-purchase') as InstantPurchaseSection) || {},
    );
    const available = computed(() => data.value.available === true);

    function buildBody(): FormData {
        const body = new FormData();
        const form = doc.querySelector<HTMLFormElement>('[data-pdp] form.pdp__form');
        if (form) {
            for (const [key, value] of new FormData(form).entries()) {
                body.append(key, value);
            }
        }
        const d = data.value;
        body.set('instant_purchase_payment_token', d.paymentToken?.publicHash ?? '');
        body.set('instant_purchase_shipping_address', String(d.shippingAddress?.id ?? ''));
        body.set('instant_purchase_billing_address', String(d.billingAddress?.id ?? ''));
        body.set('instant_purchase_carrier', d.shippingMethod?.carrier ?? '');
        body.set('instant_purchase_shipping', d.shippingMethod?.method ?? '');
        return body;
    }

    async function placeOrder(): Promise<PlaceOrderResult> {
        const response = await fetch(purchaseUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: buildBody(),
            credentials: 'same-origin',
        });
        let message = '';
        try {
            const json = (await response.json()) as { response?: string };
            message = typeof json?.response === 'string' ? json.response : '';
        } catch {
            // Non-JSON response; keep the HTTP status as the only signal.
        }
        const ok = response.ok;
        if (ok) {
            try {
                await store.reload([], { force: true });
            } catch {
                // Section refresh is non-fatal; the next navigation reconciles.
            }
        }
        return { ok, message };
    }

    return { data, available, placeOrder };
}
