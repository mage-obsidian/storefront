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
import { useCart } from "MageObsidian_Storefront::js/useCart";
import { useCustomerData } from "MageObsidian_ModernFrontend::js/customer-data";
import { ensureFormKey } from "MageObsidian_Storefront::js/form-key-provider";

const ROOT = "[data-cart-root]";

const cart = useCart();
const customerData = useCustomerData();

let busy = false;

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
    try {
        const response = await fetch(window.location.href, {
            headers: { "X-Requested-With": "XMLHttpRequest" },
            credentials: "same-origin",
        });
        const fresh = new DOMParser()
            .parseFromString(await response.text(), "text/html")
            .querySelector(ROOT);
        const current = root();
        if (fresh && current) {
            current.replaceWith(fresh);
        } else {
            window.location.reload();
        }
    } catch {
        window.location.reload();
    }
}

/**
 * Run a single mutation, then refresh. Guards against overlapping operations so a
 * second click can't race the refresh.
 */
async function run(mutate: () => Promise<unknown>): Promise<void> {
    if (busy) {
        return;
    }
    busy = true;
    root()?.setAttribute("aria-busy", "true");
    try {
        await mutate();
        await refresh();
    } finally {
        busy = false;
    }
}

function applyQty(input: HTMLInputElement): void {
    const qty = Math.max(1, parseInt(input.value, 10) || 1);
    input.value = String(qty);
    run(() => cart.updateItemQty(input.dataset.itemId, qty, endpoints().update));
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
    }
});

document.addEventListener("change", (event) => {
    const input = (event.target as HTMLElement | null)?.closest?.<HTMLInputElement>("[data-cart-qty]");
    if (within(input)) {
        applyQty(input!);
    }
});

document.addEventListener("submit", (event) => {
    const coupon = (event.target as HTMLElement | null)?.closest?.<HTMLFormElement>("[data-cart-coupon]");
    if (within(coupon)) {
        event.preventDefault();
        run(async () => {
            await fetch(coupon!.action, {
                method: "POST",
                headers: { "X-Requested-With": "XMLHttpRequest" },
                body: new FormData(coupon!),
                credentials: "same-origin",
            });
            await customerData.reload(["cart"]);
        });
        return;
    }

    const form = (event.target as HTMLElement | null)?.closest?.<HTMLFormElement>("[data-cart-form]");
    if (within(form)) {
        // The "Update bag" button / Enter: apply any quantities edited without a
        // blur, then refresh once.
        event.preventDefault();
        const inputs = [...form!.querySelectorAll<HTMLInputElement>("[data-cart-qty]")];
        run(async () => {
            for (const input of inputs) {
                const qty = Math.max(1, parseInt(input.value, 10) || 1);
                await cart.updateItemQty(input.dataset.itemId, qty, endpoints().update);
            }
        });
    }
});

// FPC-safe form key for the no-JS form fallbacks and useCart's cookie backfill.
ensureFormKey();
