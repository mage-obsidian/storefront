/**
 * Add/remove against Magento's native wishlist controllers on top of the
 * customer-data bridge. `saved` (product id → remove url) comes from the
 * MageObsidian_Wishlist section plugin, since the native section caps its item
 * list at 3 — the heart needs every membership to flag cards and toggle one off.
 */
import { computed } from 'vue';
import { useCustomerData } from 'MageObsidian_ModernFrontend::js/customer-data';
import { getFormKey } from 'MageObsidian_Storefront::js/useCart';

export function useWishlist() {
    const customerData = useCustomerData();

    const saved = computed<Record<string, string>>(
        () => (customerData.section('wishlist')?.saved as Record<string, string>) ?? {},
    );
    const count = computed(() => Object.keys(saved.value).length);
    const isLoggedIn = computed(() => Boolean(customerData.section('customer')?.firstname));

    function has(productId: number | string): boolean {
        return String(productId) in saved.value;
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
        await customerData.reload(['wishlist']);
        return ok;
    }

    function add(form: HTMLFormElement): Promise<boolean> {
        return post(form.action, new FormData(form));
    }

    function remove(productId: number | string): Promise<boolean> {
        const url = saved.value[String(productId)];
        if (!url) {
            return Promise.resolve(false);
        }
        const body = new FormData();
        body.set('form_key', getFormKey());
        return post(url, body);
    }

    return { count, saved, isLoggedIn, has, add, remove };
}

export default useWishlist;
