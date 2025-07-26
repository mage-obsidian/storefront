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

/**
 * @typedef {object} CheckoutApiConfig
 * @property {string} restBaseUrl   e.g. 'https://shop.test/rest/default/V1/'
 * @property {boolean} isLoggedIn
 * @property {string} maskedCartId  guest cart id (ignored when logged in)
 */

/**
 * @param {CheckoutApiConfig} config
 */
export function createCheckoutApi(config) {
    const { restBaseUrl = '', isLoggedIn = false, maskedCartId = '' } = config || {};
    const cartPath = isLoggedIn ? 'carts/mine' : `guest-carts/${maskedCartId}`;

    /**
     * Issue a same-origin JSON request against a cart-scoped endpoint.
     *
     * @param {'GET'|'POST'|'PUT'} method
     * @param {string} endpoint path under the cart (e.g. 'totals-information')
     * @param {object} [body]
     * @returns {Promise<any>}
     */
    async function request(method, endpoint, body) {
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

    return {
        /** Recalculate totals for a shipping address + method selection. */
        setTotalsInformation(totalsInformation) {
            return request('POST', 'totals-information', { totalsInformation });
        },
        /** Available shipping methods (rates) for an address. */
        estimateShippingMethods(address) {
            return request('POST', 'estimate-shipping-methods', { address });
        },
        /** Persist the shipping address + method; returns payment methods + totals. */
        setShippingInformation(addressInformation) {
            return request('POST', 'shipping-information', { addressInformation });
        },
        /** Payment methods available for the cart. */
        getPaymentMethods() {
            return request('GET', 'payment-methods');
        },
        /** Place the order: save payment + billing, returns the order id. */
        placeOrder(payload) {
            return request('POST', 'payment-information', payload);
        },
    };
}

/**
 * Turn Magento's `{ message, parameters }` error shape into a readable string,
 * substituting `%1`/named placeholders.
 *
 * @param {{ message?: string, parameters?: Record<string, string>|Array<string> } | null} payload
 * @returns {string}
 */
function formatError(payload) {
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
