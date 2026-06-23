/**
 * Progressive enhancement for add-to-cart. The product card ships a real
 * `<form data-add-to-cart>` that POSTs to checkout/cart/add and works with no
 * JS. This single delegated listener (one per page, not one app per card)
 * intercepts those submits, adds via `useCart` (AJAX, no reload), and announces
 * the result through a toast event. The reactive cart count updates on its own.
 */
import { useCart } from 'MageObsidian_Storefront::js/useCart';
import { ensureFormKey } from 'MageObsidian_Storefront::js/form-key-provider';
import { i18n } from 'mage-obsidian/runtime/i18nCore.ts';

const TOAST_EVENT = 'obsidian:toast';

function announce(message: string, tone: string): void {
    window.dispatchEvent(new CustomEvent(TOAST_EVENT, { detail: { message, tone } }));
}

function init(): void {
    // FPC-safe form key: the baked key may be stale on cached HTML.
    ensureFormKey();
    const cart = useCart();

    document.addEventListener('submit', async (event) => {
        const form = (event.target as HTMLElement | null)?.closest?.<HTMLFormElement>('form[data-add-to-cart]');
        if (!form) {
            return;
        }
        event.preventDefault();

        const button = form.querySelector<HTMLButtonElement>('button[type="submit"]');
        const label = button?.textContent;
        if (button) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        }

        const ok = await cart.addFromForm(form);
        announce(
            ok ? i18n.$t('Added to cart') : i18n.$t('Could not add to cart'),
            ok ? 'success' : 'error',
        );

        if (button) {
            button.disabled = false;
            button.removeAttribute('aria-busy');
            if (label !== undefined) {
                button.textContent = label;
            }
        }
    });
}

init();
