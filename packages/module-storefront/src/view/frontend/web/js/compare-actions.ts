/**
 * Delegated add-to-compare toggle for the product cards / PDP. Each ships a real
 * `<form data-add-to-compare>` that POSTs to catalog/product_compare/add and
 * works with no JS. One listener per page reflects membership (aria-pressed +
 * is-compared) reactively from the customer-data section and toggles via AJAX.
 */
import { watchEffect } from 'vue';
import { useCompare } from 'MageObsidian_Storefront::js/useCompare';
import { ensureFormKey } from 'MageObsidian_Storefront::js/form-key-provider';
import { i18n } from 'mage-obsidian/runtime/i18nCore.ts';

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
        announce(
            ok
                ? (removing ? i18n.$t('Removed from compare') : i18n.$t('Added to compare'))
                : i18n.$t('Could not update compare'),
            ok ? 'success' : 'error',
        );
        button?.removeAttribute('aria-busy');
    });
}

init();
