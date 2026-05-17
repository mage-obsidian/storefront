/**
 * `useShippingEstimator` — the cart-page "Estimate Shipping and Tax" logic, the
 * Luma estimator ported off Knockout. It reuses `createCheckoutApi` (same native
 * REST endpoints the one-page island talks to): `estimate-shipping-methods` for
 * the rates of a partial address, then `totals-information` to preview the cart
 * totals (subtotal, shipping, tax, grand total) for a picked rate WITHOUT
 * persisting anything to the quote — exactly Luma's behaviour. Vue-free and
 * stateless beyond its refs, so it unit-tests without a DOM.
 */

import { ref } from "vue";
import { createCheckoutApi, type CheckoutApiConfig } from "MageObsidian_Checkout::js/useCheckoutApi";

export interface ShippingRate {
    carrier_code: string;
    method_code: string;
    carrier_title?: string;
    method_title?: string;
    amount?: number;
    available?: boolean;
    error_message?: string;
}

export interface TotalSegment {
    code: string;
    title?: string;
    value: number | null;
}

export interface EstimatorAddress {
    country_id: string;
    region_id?: number;
    region?: string;
    postcode?: string;
}

interface TotalsResponse {
    total_segments?: TotalSegment[];
}

export function useShippingEstimator(config: CheckoutApiConfig) {
    const api = createCheckoutApi(config);

    const methods = ref<ShippingRate[]>([]);
    const selectedKey = ref<string>("");
    const segments = ref<TotalSegment[]>([]);
    const loadingRates = ref(false);
    const loadingTotals = ref(false);
    const error = ref<string>("");

    const rateKey = (rate: ShippingRate): string => `${rate.carrier_code}_${rate.method_code}`;

    async function estimate(address: EstimatorAddress): Promise<void> {
        loadingRates.value = true;
        error.value = "";
        methods.value = [];
        selectedKey.value = "";
        segments.value = [];
        try {
            const rates = (await api.estimateShippingMethods(address)) as ShippingRate[];
            methods.value = Array.isArray(rates) ? rates.filter((rate) => rate.available !== false) : [];
        } catch (err) {
            error.value = err instanceof Error ? err.message : String(err);
        } finally {
            loadingRates.value = false;
        }
    }

    async function selectMethod(address: EstimatorAddress, rate: ShippingRate): Promise<void> {
        selectedKey.value = rateKey(rate);
        loadingTotals.value = true;
        error.value = "";
        try {
            const totals = (await api.setTotalsInformation({
                address,
                shipping_method_code: rate.method_code,
                shipping_carrier_code: rate.carrier_code,
            })) as TotalsResponse;
            segments.value = Array.isArray(totals?.total_segments) ? totals.total_segments : [];
        } catch (err) {
            error.value = err instanceof Error ? err.message : String(err);
            segments.value = [];
        } finally {
            loadingTotals.value = false;
        }
    }

    return {
        methods,
        selectedKey,
        segments,
        loadingRates,
        loadingTotals,
        error,
        rateKey,
        estimate,
        selectMethod,
    };
}

export default useShippingEstimator;
