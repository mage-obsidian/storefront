// Test stub for the storefront's useCart composable
// (`MageObsidian_Storefront::js/useCart`), aliased in vitest.config.js. Records
// addProduct/addFromForm calls so the configurable island's contract — the
// super_attribute it builds and the result-driven toast — can be asserted in
// isolation. The real POST/form-key/section-reload behaviour is tested in
// module-storefront's useCart.test.js.
import { ref } from "vue";

export const __calls = [];
export const __formCalls = [];
export const __rawCalls = [];

let result = { ok: true };

export function __reset() {
    __calls.length = 0;
    __formCalls.length = 0;
    __rawCalls.length = 0;
    result = { ok: true };
}

export function __setResult(value, message) {
    result = { ok: value, message };
}

export function useCart() {
    return {
        count: ref(0),
        addProduct: (payload) => {
            __calls.push(payload);
            return Promise.resolve(result);
        },
        addFromForm: (form) => {
            __formCalls.push(form);
            return Promise.resolve(result);
        },
        addRaw: (action, body) => {
            __rawCalls.push({ action, body });
            return Promise.resolve(result);
        },
    };
}

export default useCart;
