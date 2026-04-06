/**
 * Add/remove against Magento's native product-compare controllers on top of the
 * customer-data bridge. The `compare-products` section already lists every item
 * with its id and a post-data `remove_url`, so no plugin is needed (unlike
 * wishlist) and compare works for guests too.
 */
import { computed } from 'vue';
import { useCustomerData } from 'MageObsidian_ModernFrontend::js/customer-data';
import { getFormKey } from 'MageObsidian_Storefront::js/useCart';

interface CompareItem {
    id: number | string;
    remove_url?: string;
}

export function useCompare() {
    const customerData = useCustomerData();

    const items = computed<CompareItem[]>(
        () => (customerData.section('compare-products')?.items as CompareItem[]) ?? [],
    );
    const count = computed(() => items.value.length);
    const listUrl = computed(() => String(customerData.section('compare-products')?.listUrl ?? ''));

    function has(productId: number | string): boolean {
        return items.value.some((item) => String(item.id) === String(productId));
    }

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
        await customerData.reload(['compare-products']);
        return ok;
    }

    function add(form: HTMLFormElement): Promise<boolean> {
        return post(form.action, new FormData(form));
    }

    // The section's remove_url is a `{action, data}` post-data envelope.
    function remove(productId: number | string): Promise<boolean> {
        const item = items.value.find((entry) => String(entry.id) === String(productId));
        if (!item?.remove_url) {
            return Promise.resolve(false);
        }
        let parsed: { action?: string; data?: Record<string, unknown> };
        try {
            parsed = JSON.parse(item.remove_url);
        } catch {
            return Promise.resolve(false);
        }
        if (!parsed.action) {
            return Promise.resolve(false);
        }
        const body = new FormData();
        for (const [key, value] of Object.entries(parsed.data ?? {})) {
            body.set(key, String(value));
        }
        return post(parsed.action, body);
    }

    return { count, items, listUrl, has, add, remove };
}

export default useCompare;
