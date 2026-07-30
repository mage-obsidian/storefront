// Test stub for the storefront's form-key provider
// (`MageObsidian_Storefront::js/form-key-provider`), aliased in vitest.config.js.
// The real one seeds the form_key cookie and registers a submit listener; here it
// just returns a fixed key so the island's hidden input has something to assert.
export const STUB_FORM_KEY = 'STUBKEY123456789';

export function getFormKey(): string {
    return STUB_FORM_KEY;
}

export function ensureFormKey(): string {
    return STUB_FORM_KEY;
}

export default ensureFormKey;
