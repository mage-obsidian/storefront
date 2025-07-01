// Test stub for the storefront's form-key provider
// (`MageObsidian_Storefront::js/form-key-provider`), aliased in vitest.config.js.
// The real one seeds the form_key cookie and aligns inputs; here it is a no-op so
// the cart-page enhancer imports cleanly under test.
export function ensureFormKey() {}

export default ensureFormKey;
