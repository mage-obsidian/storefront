/**
 * Add-to-cart enhancer for non-configurable products that carry custom options
 * (a `<form data-product-form>` with a server-rendered options block). It owns
 * the whole form rather than the generic cart-actions listener so it can: show a
 * live total (base price + option deltas), validate required options accessibly
 * before submitting, and AJAX-add via the form (FormData captures every option
 * field, including file uploads). With JS off the native POST still works.
 *
 * The configurable buy box reuses the same product-options logic from inside its
 * Vue island; this enhancer is the simple-product host.
 */
import { useCart } from "MageObsidian_Storefront::js/useCart";
import { createProductOptions } from "MageObsidian_Catalog::js/product-options";

const TOAST_EVENT = "obsidian:toast";

function announce(message: string, tone: string): void {
    if (message) {
        window.dispatchEvent(new CustomEvent(TOAST_EVENT, { detail: { message, tone } }));
    }
}

function formatTotal(format: string, amount: number): string {
    return format.replace("%s", amount.toFixed(2));
}

export function setup(form: HTMLFormElement): void {
    const root = form.querySelector<HTMLElement>("[data-product-options]");
    const options = root ? createProductOptions(root) : null;
    const base = Number(form.dataset.basePrice ?? "0");
    const format = form.dataset.currencyFormat ?? "%s";
    const totalEl = form.querySelector<HTMLElement>("[data-options-total]");

    const renderTotal = (): void => {
        if (totalEl) {
            totalEl.textContent = formatTotal(format, base + (options?.delta() ?? 0));
        }
    };
    options?.onChange(renderTotal);
    renderTotal();

    // With JS active we validate; the native required attributes still guard the
    // no-JS path.
    form.noValidate = true;
    const cart = useCart();

    form.addEventListener("submit", async (event) => {
        event.preventDefault();
        if (options && !options.validate()) {
            return;
        }
        const button = form.querySelector<HTMLButtonElement>('button[type="submit"]');
        if (button) {
            button.disabled = true;
            button.setAttribute("aria-busy", "true");
        }

        // Magento's own wording wins when it explains the failure (bad file
        // extension, missing required option); the data-msg-* copy is the fallback.
        const { ok, message } = await cart.addFromForm(form);
        announce(
            message ?? (ok ? form.dataset.msgAdded ?? "Added to cart" : form.dataset.msgFailed ?? "Could not add to cart"),
            ok ? "success" : "error",
        );

        if (button) {
            button.disabled = false;
            button.removeAttribute("aria-busy");
        }
    });
}

export function init(): void {
    document.querySelectorAll<HTMLFormElement>("[data-product-form]").forEach(setup);
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
} else {
    init();
}
