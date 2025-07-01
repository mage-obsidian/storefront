// Test stub for the engine's customer-data bridge
// (`MageObsidian_ModernFrontend::js/customer-data`), aliased in vitest.config.js.
// Holds a reactive section map so the mini-cart reacts to `__setSection`, and
// records `reload()` calls so the qty/remove flow can be asserted.
import { ref } from "vue";

const sections = ref({});

export function __setSection(name, value) {
    sections.value = { ...sections.value, [name]: value };
}

export function __reset() {
    sections.value = {};
    reload.calls = [];
}

export function reload(...args) {
    reload.calls.push(args);
    return Promise.resolve();
}
reload.calls = [];

export function useCustomerData() {
    return {
        section: (name) => sections.value[name] ?? null,
        reload,
    };
}

export default useCustomerData;
