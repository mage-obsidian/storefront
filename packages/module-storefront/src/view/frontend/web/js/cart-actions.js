/**
 * Progressive enhancement for add-to-cart. The product card ships a real
 * `<form data-add-to-cart>` that POSTs to checkout/cart/add and works with no
 * JS. This single delegated listener (one per page, not one app per card)
 * intercepts those submits, adds via `useCart` (AJAX, no reload), and announces
 * the result through a toast event. The reactive cart count updates on its own.
 */
import { useCart } from './useCart.js';
import { ensureFormKey } from './form-key-provider.js';

const TOAST_EVENT = 'obsidian:toast';

function announce(message, tone) {
    window.dispatchEvent(new CustomEvent(TOAST_EVENT, { detail: { message, tone } }));
}

function init() {
    // FPC-safe form key: the baked key may be stale on cached HTML.
    ensureFormKey();
    const cart = useCart();

    document.addEventListener('submit', async (event) => {
        const form = event.target.closest?.('form[data-add-to-cart]');
        if (!form) {
            return;
        }
        event.preventDefault();

        const button = form.querySelector('button[type="submit"]');
        const label = button?.textContent;
        if (button) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        }

        const ok = await cart.addFromForm(form);
        announce(
            ok ? window.__OBSIDIAN_CART_I18N__?.added ?? 'Added to cart'
               : window.__OBSIDIAN_CART_I18N__?.failed ?? 'Could not add to cart',
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
