/**
 * `useCart` — thin add-to-cart action on top of the engine's `useCustomerData`
 * bridge. We deliberately reuse Magento's native session quote (POST to
 * `checkout/cart/add` with the form key) instead of a separate GraphQL/masked
 * cart, so the cart page, mini-cart and checkout (later waves) all see ONE cart.
 * After a successful add, Magento invalidates the `cart` section, so we reload
 * it — the reactive `count` (and everything else reading `section('cart')`)
 * updates everywhere, FPC-safe.
 */
import { computed } from 'vue';
import { useCustomerData } from 'MageObsidian_ModernFrontend::js/customer-data';

/** Read Magento's form key from its cookie (set by the page-cache layer). */
export function getFormKey(): string {
    const match = typeof document !== 'undefined'
        ? document.cookie.match(/(?:^|;\s*)form_key=([^;]+)/)
        : null;
    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Flatten a fields object into FormData, expanding one nested level into
 * `parent[child]` keys (how Magento expects `super_attribute[attrId]`).
 */
function toFormData(fields: Record<string, unknown>): FormData {
    const body = new FormData();
    for (const [key, value] of Object.entries(fields)) {
        if (value === undefined || value === null) {
            continue;
        }
        if (typeof value === 'object') {
            for (const [childKey, childValue] of Object.entries(value)) {
                body.set(`${key}[${childKey}]`, String(childValue));
            }
        } else {
            body.set(key, String(value));
        }
    }
    return body;
}

export function useCart() {
    const customerData = useCustomerData();

    const count = computed(() => Number(customerData.section('cart')?.summary_count ?? 0));

    /**
     * POST a prepared body to the add-to-cart endpoint and refresh the cart
     * section. Backfills the form key from the cookie if a cached page shipped
     * without it. Always reloads the section afterwards so reactive state stays
     * consistent even on failure.
     */
    async function post(action: string, body: FormData): Promise<boolean> {
        if (!body.get('form_key')) {
            body.set('form_key', getFormKey());
        }
        let ok = false;
        try {
            const response = await fetch(action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body,
                credentials: 'same-origin',
            });
            ok = response.ok;
        } catch {
            ok = false;
        }
        await customerData.reload(['cart']);
        return ok;
    }

    /**
     * Add from a server-rendered add-to-cart form (simple/virtual/downloadable).
     */
    function addFromForm(form: HTMLFormElement): Promise<boolean> {
        return post(form.action, new FormData(form));
    }

    /**
     * Add a product by explicit fields — used by the configurable island, which
     * builds `super_attribute` from the chosen swatches.
     *
     * The form key is backfilled from the cookie by post() (kept fresh by the
     * form-key provider), so it is not threaded through here.
     */
    function addProduct({ action, product, qty = 1, uenc, superAttribute }: {
        action: string;
        product: number | string;
        qty?: number;
        uenc?: string;
        superAttribute?: Record<string, number | string>;
    }): Promise<boolean> {
        return post(action, toFormData({
            product,
            qty,
            uenc,
            super_attribute: superAttribute,
        }));
    }

    /**
     * Add from a pre-built FormData body — used by the configurable island when
     * the product also has custom options: it assembles super_attribute plus the
     * option fields (including file uploads, which need multipart) and posts the
     * lot. The form key is backfilled by post().
     */
    function addRaw(action: string, body: FormData): Promise<boolean> {
        return post(action, body);
    }

    /**
     * Change a line item's quantity from the mini-cart, via Magento's native
     * sidebar endpoint (which invalidates the `cart` section; we reload it so the
     * lines, subtotal and badge update reactively). The endpoint URL is provided
     * by the server (`checkout/sidebar/updateItemQty`) so store-code/secure-base
     * resolution stays correct.
     */
    function updateItemQty(itemId: number | string, qty: number | string, action: string): Promise<boolean> {
        return post(action, toFormData({ item_id: itemId, item_qty: qty }));
    }

    /**
     * Remove a line item from the mini-cart, via Magento's native sidebar
     * endpoint (`checkout/sidebar/removeItem`). Reloads the `cart` section after.
     */
    function removeItem(itemId: number | string, action: string): Promise<boolean> {
        return post(action, toFormData({ item_id: itemId }));
    }

    return { count, addFromForm, addProduct, addRaw, updateItemQty, removeItem };
}

export default useCart;
