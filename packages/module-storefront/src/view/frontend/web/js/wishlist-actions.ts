/**
 * Delegated heart toggle for the product cards / PDP. Each ships a real
 * `<form data-add-to-wishlist>` that POSTs to wishlist/index/add and works with
 * no JS. One listener per page reflects membership on every heart (filled +
 * aria-pressed) reactively from the customer-data wishlist section, and toggles
 * add/remove via AJAX. Guests fall through to the native POST (redirect to login).
 */
import { watchEffect } from 'vue';
import { useWishlist } from 'MageObsidian_Storefront::js/useWishlist';
import { ensureFormKey } from 'MageObsidian_Storefront::js/form-key-provider';
import { i18n } from 'mage-obsidian/runtime/i18nCore.ts';

const TOAST_EVENT = 'obsidian:toast';

function announce(message: string, tone: string): void {
    window.dispatchEvent(new CustomEvent(TOAST_EVENT, { detail: { message, tone } }));
}

function init(): void {
    ensureFormKey();
    const wishlist = useWishlist();

    watchEffect(() => {
        const saved = wishlist.saved.value;
        document.querySelectorAll<HTMLElement>('[data-add-to-wishlist]').forEach((form) => {
            const inList = String(form.dataset.productId ?? '') in saved;
            form.classList.toggle('is-saved', inList);
            form.querySelector('button')?.setAttribute('aria-pressed', inList ? 'true' : 'false');
        });
    });

    document.addEventListener('submit', async (event) => {
        const form = (event.target as HTMLElement | null)?.closest?.<HTMLFormElement>('form[data-add-to-wishlist]');
        if (!form) {
            return;
        }
        if (!wishlist.isLoggedIn.value) {
            return;
        }
        event.preventDefault();

        const id = form.dataset.productId ?? '';
        const button = form.querySelector<HTMLButtonElement>('button');
        const removing = wishlist.has(id);
        button?.setAttribute('aria-busy', 'true');

        const ok = removing ? await wishlist.remove(id) : await wishlist.add(form);
        announce(
            ok
                ? (removing ? i18n.$t('Removed from wish list') : i18n.$t('Added to wish list'))
                : i18n.$t('Could not update wish list'),
            ok ? 'success' : 'error',
        );
        button?.removeAttribute('aria-busy');
    });
}

init();
