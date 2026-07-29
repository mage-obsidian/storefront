/**
 * Add/remove against Magento's native wishlist controllers on top of the
 * customer-data bridge. `saved` (product id → remove url) comes from the
 * MageObsidian_Wishlist section plugin, since the native section caps its item
 * list at 3 — the heart needs every membership to flag cards and toggle one off.
 */
import { computed } from 'vue';
import { useCustomerData } from 'MageObsidian_ModernFrontend::js/customer-data';
import events from 'MageObsidian_ModernFrontend::js/events';
import { getFormKey } from 'MageObsidian_Storefront::js/useCart';
import {
    MutationPhase,
    mutationEvent,
    type MutationEvent,
    type MutationEventName,
} from 'mage-obsidian/runtime/mutationEvent.ts';

export const WISHLIST_DOMAIN = 'wishlist';

export const WishlistOperation = {
    Add: 'add',
    Remove: 'remove',
} as const;

export type WishlistOperation = (typeof WishlistOperation)[keyof typeof WishlistOperation];

export type WishlistEvent = MutationEvent<WishlistOperation, boolean>;

export type WishlistEventName = MutationEventName<typeof WISHLIST_DOMAIN, WishlistOperation>;

declare module 'mage-obsidian/runtime/eventManager.ts' {
    interface StorefrontEventMap extends Record<WishlistEventName, WishlistEvent> {}
}

const WISHLIST_SECTION = 'wishlist';
const CUSTOMER_SECTION = 'customer';

export function useWishlist() {
    const customerData = useCustomerData();

    const saved = computed<Record<string, string>>(
        () => (customerData.section(WISHLIST_SECTION)?.saved as Record<string, string>) ?? {},
    );
    const count = computed(() => Object.keys(saved.value).length);
    const isLoggedIn = computed(() => Boolean(customerData.section(CUSTOMER_SECTION)?.firstname));

    function has(productId: number | string): boolean {
        return String(productId) in saved.value;
    }

    async function post(
        operation: WishlistOperation,
        action: string,
        body: FormData,
    ): Promise<boolean> {
        if (!body.get('form_key')) {
            body.set('form_key', getFormKey());
        }

        const request = await events.dispatch(
            mutationEvent(WISHLIST_DOMAIN, operation, MutationPhase.Before),
            { operation, action, body, cancelled: false },
        );
        if (request.cancelled) {
            return false;
        }

        let ok = false;
        try {
            const response = await fetch(request.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: request.body,
                credentials: 'same-origin',
            });
            ok = response.ok;
        } catch {
            ok = false;
        }
        await customerData.reload([WISHLIST_SECTION]);

        await events.dispatch(mutationEvent(WISHLIST_DOMAIN, operation, MutationPhase.After), {
            ...request,
            result: ok,
        });
        if (!ok) {
            await events.dispatch(mutationEvent(WISHLIST_DOMAIN, operation, MutationPhase.Failed), {
                ...request,
                result: ok,
            });
        }

        return ok;
    }

    function add(form: HTMLFormElement): Promise<boolean> {
        return post(WishlistOperation.Add, form.action, new FormData(form));
    }

    function remove(productId: number | string): Promise<boolean> {
        const url = saved.value[String(productId)];
        if (!url) {
            return Promise.resolve(false);
        }
        const body = new FormData();
        body.set('form_key', getFormKey());
        return post(WishlistOperation.Remove, url, body);
    }

    return { count, saved, isLoggedIn, has, add, remove };
}

export default useWishlist;
