// Test stub for the storefront's useCart composable
// (`MageObsidian_Storefront::js/useCart`), aliased in vitest.config.js. Records
// updateItemQty/removeItem calls so the mini-cart's mutation contract can be
// asserted in isolation. The real POST/form-key/section-reload behaviour is
// tested in module-storefront's useCart.test.js.
import { ref } from "vue";

export const __calls = [];

let result = true;

export function __reset() {
    __calls.length = 0;
    result = true;
}

export function __setResult(value) {
    result = value;
}

export function useCart() {
    return {
        count: ref(0),
        updateItemQty: (itemId, qty, action) => {
            __calls.push({ type: "updateItemQty", itemId, qty, action });
            return Promise.resolve(result);
        },
        removeItem: (itemId, action) => {
            __calls.push({ type: "removeItem", itemId, action });
            return Promise.resolve(result);
        },
        addProduct: (payload) => {
            __calls.push({ type: "addProduct", ...payload });
            return Promise.resolve(result);
        },
        addFromForm: () => Promise.resolve(result),
    };
}

export default useCart;
