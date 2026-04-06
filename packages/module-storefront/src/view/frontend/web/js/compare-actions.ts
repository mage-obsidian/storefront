/**
 * Delegated add-to-compare toggle for the product cards / PDP. Each ships a real
 * `<form data-add-to-compare>` that POSTs to catalog/product_compare/add and
 * works with no JS. One listener per page reflects membership (aria-pressed +
 * is-compared) reactively from the customer-data section and toggles via AJAX.
 */
import { watchEffect } from 'vue';
import { useCompare } from 'MageObsidian_Storefront::js/useCompare';
import { ensureFormKey } from 'MageObsidian_Storefront::js/form-key-provider';

declare global {
    interface Window {
        __OBSIDIAN_COMPARE_I18N__?: { added?: string; removed?: string; failed?: string };
    }
}

const TOAST_EVENT = 'obsidian:toast';

function announce(message: string, tone: string): void {
    window.dispatchEvent(new CustomEvent(TOAST_EVENT, { detail: { message, tone } }));
}

function init(): void {
    ensureFormKey();
    const compare = useCompare();

    watchEffect(() => {
        const items = compare.items.value;
        const ids = new Set(items.map((item) => String(item.id)));
        document.querySelectorAll<HTMLElement>('[data-add-to-compare]').forEach((form) => {
            const inList = ids.has(String(form.dataset.productId ?? ''));
            form.classList.toggle('is-compared', inList);
            form.querySelector('button')?.setAttribute('aria-pressed', inList ? 'true' : 'false');
        });
    });

    document.addEventListener('submit', async (event) => {
        const form = (event.target as HTMLElement | null)?.closest?.<HTMLFormElement>('form[data-add-to-compare]');
        if (!form) {
            return;
        }
        event.preventDefault();

        const id = form.dataset.productId ?? '';
        const button = form.querySelector<HTMLButtonElement>('button');
        const removing = compare.has(id);
        button?.setAttribute('aria-busy', 'true');

        const ok = removing ? await compare.remove(id) : await compare.add(form);
        const i18n = window.__OBSIDIAN_COMPARE_I18N__ ?? {};
        announce(
            ok
                ? (removing ? i18n.removed ?? 'Removed from compare' : i18n.added ?? 'Added to compare')
                : i18n.failed ?? 'Could not update compare',
            ok ? 'success' : 'error',
        );
        button?.removeAttribute('aria-busy');
    });
}

init();
