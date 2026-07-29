/**
 * Add/remove against Magento's native product-compare controllers on top of the
 * customer-data bridge. The `compare-products` section already lists every item
 * with its id and a post-data `remove_url`, so no plugin is needed (unlike
 * wishlist) and compare works for guests too.
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

interface CompareItem {
    id: number | string;
    remove_url?: string;
}

export const COMPARE_DOMAIN = 'compare';

export const CompareOperation = {
    Add: 'add',
    Remove: 'remove',
} as const;

export type CompareOperation = (typeof CompareOperation)[keyof typeof CompareOperation];

export type CompareEvent = MutationEvent<CompareOperation, boolean>;

export type CompareEventName = MutationEventName<typeof COMPARE_DOMAIN, CompareOperation>;

declare module 'mage-obsidian/runtime/eventManager.ts' {
    interface StorefrontEventMap extends Record<CompareEventName, CompareEvent> {}
}

const COMPARE_SECTION = 'compare-products';

export function useCompare() {
    const customerData = useCustomerData();

    const items = computed<CompareItem[]>(
        () => (customerData.section(COMPARE_SECTION)?.items as CompareItem[]) ?? [],
    );
    const count = computed(() => items.value.length);
    const listUrl = computed(() => String(customerData.section(COMPARE_SECTION)?.listUrl ?? ''));

    function has(productId: number | string): boolean {
        return items.value.some((item) => String(item.id) === String(productId));
    }

    async function post(
        operation: CompareOperation,
        action: string,
        body: FormData,
    ): Promise<boolean> {
        if (!body.get('form_key')) {
            body.set('form_key', getFormKey());
        }

        const request = await events.dispatch(
            mutationEvent(COMPARE_DOMAIN, operation, MutationPhase.Before),
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
        await customerData.reload([COMPARE_SECTION]);

        await events.dispatch(mutationEvent(COMPARE_DOMAIN, operation, MutationPhase.After), {
            ...request,
            result: ok,
        });
        if (!ok) {
            await events.dispatch(mutationEvent(COMPARE_DOMAIN, operation, MutationPhase.Failed), {
                ...request,
                result: ok,
            });
        }

        return ok;
    }

    function add(form: HTMLFormElement): Promise<boolean> {
        return post(CompareOperation.Add, form.action, new FormData(form));
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
        return post(CompareOperation.Remove, parsed.action, body);
    }

    return { count, items, listUrl, has, add, remove };
}

export default useCompare;
