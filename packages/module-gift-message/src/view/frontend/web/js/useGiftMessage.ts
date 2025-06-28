/**
 * `useGiftMessage` — thin wrapper over Magento's native gift-message REST
 * endpoints (the same ones the Knockout cart used). It resolves the cart path by
 * auth mode (logged-in → `carts/mine`, session-authenticated same-origin; guest
 * → `guest-carts/:maskedId`, authorised by possession of the masked id) so
 * callers never branch on it. Order-level messages post to `.../gift-message`;
 * per-item ones to `.../gift-message/:itemId`. A non-2xx response throws an Error
 * carrying Magento's message.
 */

export interface GiftMessageEndpoint {
    restBaseUrl: string;
    isLoggedIn: boolean;
    maskedCartId: string;
}

export interface GiftMessageData {
    sender: string;
    recipient: string;
    message: string;
}

interface MagentoError {
    message?: string;
    parameters?: Record<string, string> | string[];
}

// Magento honours the customer session for the Web API only on XHR requests
// (CustomerSessionUserContext), so a logged-in `carts/mine` call without this
// header is rejected as guest. fetch does not set it; we must.
const JSON_HEADERS = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

export function useGiftMessage(endpoint: GiftMessageEndpoint) {
    const { restBaseUrl = '', isLoggedIn = false, maskedCartId = '' } = endpoint || {};
    const cartPath = isLoggedIn ? 'carts/mine' : `guest-carts/${maskedCartId}`;

    async function post(path: string, data: GiftMessageData): Promise<boolean> {
        const response = await fetch(`${restBaseUrl}${cartPath}/${path}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: JSON_HEADERS,
            body: JSON.stringify({ giftMessage: data }),
        });
        const payload = await response.json().catch(() => null);
        if (!response.ok) {
            throw new Error(formatError(payload) || `Gift message request failed (${response.status})`);
        }
        return true;
    }

    return {
        /** Save (or clear) the order-level gift message. */
        saveOrderMessage(data: GiftMessageData): Promise<boolean> {
            return post('gift-message', data);
        },
        /** Save (or clear) a cart item's gift message. */
        saveItemMessage(itemId: number | string, data: GiftMessageData): Promise<boolean> {
            return post(`gift-message/${itemId}`, data);
        },
    };
}

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

export default useGiftMessage;
