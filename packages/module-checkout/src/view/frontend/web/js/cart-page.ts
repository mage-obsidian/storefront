/**
 * Progressive enhancement for the shopping bag page. The page is fully
 * server-rendered and its forms POST to Magento's native cart controllers, so it
 * works with no JS. This single set of delegated listeners (bound once, on the
 * document) upgrades those interactions to AJAX: quantity steppers and removal go
 * through `useCart`'s sidebar endpoints (which reload the `cart` section, so the
 * header badge and mini-cart stay in sync), the coupon form POSTs in place, and
 * after each mutation we re-fetch the page and morph the server-authoritative
 * cart region — totals/tax/discount are never recomputed client-side. Delegation
 * on the document means the swapped-in DOM keeps working without re-binding.
 */
import { useCart, getFormKey, CART_DOMAIN } from "MageObsidian_Storefront::js/useCart";
import events from "MageObsidian_ModernFrontend::js/events";
import {
    MutationPhase,
    mutationEvent,
    type MutationEvent,
    type MutationEventName,
} from "mage-obsidian/runtime/mutationEvent.ts";
import { useCustomerData } from "MageObsidian_ModernFrontend::js/customer-data";
import { ensureFormKey } from "MageObsidian_Storefront::js/form-key-provider";
import { createCartQueue } from "MageObsidian_Checkout::js/cart-queue";

const ROOT = "[data-cart-root]";

export const CartPageOperation = {
    Coupon: "coupon",
} as const;

export type CartPageOperation = (typeof CartPageOperation)[keyof typeof CartPageOperation];

export type CartCouponEvent = MutationEvent<CartPageOperation, boolean>;

export type CartCouponEventName = MutationEventName<typeof CART_DOMAIN, CartPageOperation>;

declare module "mage-obsidian/runtime/eventManager.ts" {
    interface StorefrontEventMap extends Record<CartCouponEventName, CartCouponEvent> {}
}

const couponEvent = <Phase extends MutationPhase>(phase: Phase) =>
    mutationEvent(CART_DOMAIN, CartPageOperation.Coupon, phase);

const CART_SECTION = "cart";
const WISHLIST_SECTION = "wishlist";

const cart = useCart();
const customerData = useCustomerData();

const root = (): HTMLElement | null => document.querySelector<HTMLElement>(ROOT);
const within = (el: Element | null | undefined): boolean => !!el && !!root()?.contains(el);

function endpoints(): { update?: string; remove?: string } {
    const el = root();
    return { update: el?.dataset.updateUrl, remove: el?.dataset.removeUrl };
}

/**
 * Re-fetch the cart page and swap the server-rendered cart region in place, so
 * totals/discount reflect the authoritative server state. Falls back to a full
 * reload if anything is off.
 */
async function refresh(): Promise<void> {
    let fresh: Element | null = null;
    try {
        const response = await fetch(window.location.href, {
            headers: { "X-Requested-With": "XMLHttpRequest" },
            credentials: "same-origin",
        });
        fresh = new DOMParser()
            .parseFromString(await response.text(), "text/html")
            .querySelector(ROOT);
    } catch {
        fresh = null;
    }

    const current = root();
    if (!fresh || !current) {
        window.location.reload();

        return;
    }

    const swap = (): void => {
        current.replaceWith(fresh as Element);
    };

    // Each line carries its own `view-transition-name`, so the browser fades out
    // the row that went and slides the ones below into place; without this the
    // replaced region snaps and everything under it jumps.
    const start = document.startViewTransition?.bind(document);
    if (!start) {
        swap();

        return;
    }
    // `updateCallbackDone`, not `finished`: the DOM is ready once the callback
    // ran, and the queue should not wait out the animation.
    await start(swap).updateCallbackDone.catch(() => {});
}

const queue = createCartQueue({
    updateQty: async (itemId, qty) => {
        await cart.updateItemQty(itemId, qty, endpoints().update);
    },
    settle: refresh,
    // Re-queried every time: `refresh` replaces the node this was set on.
    onBusyChange: (busy) => {
        const el = root();
        if (busy) {
            el?.setAttribute("aria-busy", "true");
        } else {
            el?.removeAttribute("aria-busy");
        }
    },
});

const run = (mutate: () => Promise<unknown>): Promise<void> =>
    queue.mutate(async () => {
        await mutate();
    });

function applyQty(input: HTMLInputElement): void {
    const qty = Math.max(1, parseInt(input.value, 10) || 1);
    input.value = String(qty);
    if (input.dataset.itemId) {
        queue.setQty(input.dataset.itemId, qty);
    }
}

document.addEventListener("click", (event) => {
    const step = (event.target as HTMLElement | null)?.closest?.<HTMLElement>("[data-cart-step]");
    if (within(step)) {
        const input = step!.closest("[data-cart-line]")?.querySelector<HTMLInputElement>("[data-cart-qty]");
        if (!input) {
            return;
        }
        const next = Math.max(1, (parseInt(input.value, 10) || 1) + parseInt(step!.dataset.cartStep ?? "0", 10));
        if (next === (parseInt(input.value, 10) || 1)) {
            return;
        }
        input.value = String(next);
        applyQty(input);
        return;
    }

    const remove = (event.target as HTMLElement | null)?.closest?.<HTMLElement>("[data-cart-remove]");
    if (within(remove)) {
        event.preventDefault();
        run(() => cart.removeItem(remove!.dataset.itemId, endpoints().remove));
        return;
    }

    const move = (event.target as HTMLElement | null)?.closest?.<HTMLElement>("[data-cart-move]");
    if (within(move)) {
        event.preventDefault();
        run(() => moveToWishlist(move!.dataset.moveUrl, move!.dataset.itemId));
    }
});

/**
 * Move a line to the wish list via the native wishlist/index/fromcart controller
 * (it removes the cart line and adds the product to the wish list server-side),
 * then reload both customer-data sections so the cart and the header wish-list
 * badge stay in sync. The form key is backfilled from the cookie (FPC-safe).
 */
async function moveToWishlist(action: string | undefined, itemId: string | undefined): Promise<void> {
    if (!action || !itemId) {
        return;
    }
    const body = new FormData();
    body.set("item", itemId);
    body.set("form_key", getFormKey());
    try {
        await fetch(action, {
            method: "POST",
            headers: { "X-Requested-With": "XMLHttpRequest" },
            body,
            credentials: "same-origin",
        });
    } finally {
        await customerData.reload([CART_SECTION, WISHLIST_SECTION]);
    }
}

document.addEventListener("change", (event) => {
    const input = (event.target as HTMLElement | null)?.closest?.<HTMLInputElement>("[data-cart-qty]");
    if (within(input)) {
        applyQty(input!);
    }
});

async function applyCoupon(form: HTMLFormElement): Promise<void> {
    const request = await events.dispatch(couponEvent(MutationPhase.Before), {
        operation: CartPageOperation.Coupon,
        action: form.action,
        body: new FormData(form),
        cancelled: false,
    });
    if (request.cancelled) {
        return;
    }

    let ok = false;
    try {
        const response = await fetch(request.action, {
            method: "POST",
            headers: { "X-Requested-With": "XMLHttpRequest" },
            body: request.body,
            credentials: "same-origin",
        });
        ok = response.ok;
    } catch {
        ok = false;
    }
    await customerData.reload([CART_SECTION]);

    await events.dispatch(couponEvent(MutationPhase.After), { ...request, result: ok });
    if (!ok) {
        await events.dispatch(couponEvent(MutationPhase.Failed), { ...request, result: ok });
    }
}

document.addEventListener("submit", (event) => {
    const coupon = (event.target as HTMLElement | null)?.closest?.<HTMLFormElement>("[data-cart-coupon]");
    if (within(coupon)) {
        event.preventDefault();
        run(() => applyCoupon(coupon!));
        return;
    }

    const form = (event.target as HTMLElement | null)?.closest?.<HTMLFormElement>("[data-cart-form]");
    if (within(form)) {
        // The "Update bag" button / Enter: apply any quantities edited without a
        // blur, then refresh once.
        event.preventDefault();
        for (const input of form!.querySelectorAll<HTMLInputElement>("[data-cart-qty]")) {
            applyQty(input);
        }
        void run(async () => {});
    }
});

// "Update bag" only exists for the no-JS path: with the enhancer running, every
// quantity is already applied by the time the button could be pressed. The flag
// goes on the document because `refresh` replaces the cart region wholesale.
document.documentElement.setAttribute("data-cart-enhanced", "");

// FPC-safe form key for the no-JS form fallbacks and useCart's cookie backfill.
ensureFormKey();
