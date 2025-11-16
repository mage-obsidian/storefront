/**
 * `createCheckoutApi` — a thin wrapper over Magento's native checkout REST
 * endpoints, the same ones the Knockout one-page uses. It resolves the cart path
 * by auth mode (logged-in → `carts/mine`, authenticated by the PHP session
 * cookie same-origin; guest → `guest-carts/:maskedId`, authorised by possession
 * of the masked id) so callers never branch on it. All requests are same-origin
 * and JSON; a non-2xx response throws an Error carrying Magento's message.
 *
 * Stateless and Vue-free so it is unit-testable; the Pinia `useCheckout` store
 * wraps it with reactive step state.
 */

export interface CheckoutApiConfig {
    /** e.g. 'https://shop.test/rest/default/V1/' */
    restBaseUrl?: string;
    isLoggedIn?: boolean;
    /** guest cart id (ignored when logged in) */
    maskedCartId?: string;
}

interface MagentoError {
    message?: string;
    parameters?: Record<string, string> | string[];
}

type HttpMethod = 'GET' | 'POST' | 'PUT' | 'DELETE';

export function createCheckoutApi(config: CheckoutApiConfig) {
    const { restBaseUrl = '', isLoggedIn = false, maskedCartId = '' } = config || {};
    const cartPath = isLoggedIn ? 'carts/mine' : `guest-carts/${maskedCartId}`;

    /** Issue a same-origin JSON request against a cart-scoped endpoint. */
    async function request(method: HttpMethod, endpoint: string, body?: unknown): Promise<unknown> {
        const response = await fetch(`${restBaseUrl}${cartPath}/${endpoint}`, {
            method,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: body === undefined ? undefined : JSON.stringify(body),
        });

        const payload = await response.json().catch(() => null);
        if (!response.ok) {
            throw new Error(formatError(payload) || `Checkout request failed (${response.status})`);
        }
        return payload;
    }

    /** Issue a same-origin JSON request against a non-cart-scoped endpoint. */
    async function rootRequest(method: HttpMethod, endpoint: string, body?: unknown): Promise<unknown> {
        const response = await fetch(`${restBaseUrl}${endpoint}`, {
            method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: body === undefined ? undefined : JSON.stringify(body),
        });
        const payload = await response.json().catch(() => null);
        if (!response.ok) {
            throw new Error(formatError(payload as MagentoError | null) || `Checkout request failed (${response.status})`);
        }
        return payload;
    }

    return {
        /**
         * Whether the email has no existing account (true → free to check out as a
         * guest; false → an account exists and the shopper should sign in). Native
         * `customers/isEmailAvailable`, not cart-scoped.
         */
        isEmailAvailable(customerEmail: string, websiteId?: number) {
            const body: Record<string, unknown> = { customerEmail };
            if (websiteId !== undefined) {
                body.websiteId = websiteId;
            }
            return rootRequest('POST', 'customers/isEmailAvailable', body) as Promise<boolean>;
        },
        /** Recalculate totals for a shipping address + method selection. */
        setTotalsInformation(totalsInformation: unknown) {
            return request('POST', 'totals-information', { totalsInformation });
        },
        /** Available shipping methods (rates) for an address. */
        estimateShippingMethods(address: unknown) {
            return request('POST', 'estimate-shipping-methods', { address });
        },
        /** Persist the shipping address + method; returns payment methods + totals. */
        setShippingInformation(addressInformation: unknown) {
            return request('POST', 'shipping-information', { addressInformation });
        },
        /** Payment methods available for the cart. */
        getPaymentMethods() {
            return request('GET', 'payment-methods');
        },
        getTotals() {
            return request('GET', 'totals');
        },
        applyCoupon(code: string) {
            return request('PUT', `coupons/${encodeURIComponent(code)}`) as Promise<boolean>;
        },
        removeCoupon() {
            return request('DELETE', 'coupons') as Promise<boolean>;
        },
        /** Place the order: save payment + billing, returns the order id. */
        placeOrder(payload: unknown) {
            return request('POST', 'payment-information', payload);
        },
    };
}

/**
 * Turn Magento's `{ message, parameters }` error shape into a readable string,
 * substituting `%1`/named placeholders.
 */
function formatError(payload: MagentoError | null): string {
    if (!payload || typeof payload.message !== 'string') {
        return '';
    }
    const params = payload.parameters;
    if (!params) {
        return payload.message;
    }
    return payload.message.replace(/%(\w+)/g, (match, key) => {
        if (Array.isArray(params)) {
            const value = params[Number(key) - 1];
            return value === undefined ? match : String(value);
        }
        return params[key] === undefined ? match : String(params[key]);
    });
}

export default createCheckoutApi;
