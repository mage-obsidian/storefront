import { defineConfig } from "vitest/config";
import vue from "@vitejs/plugin-vue";
import { fileURLToPath } from "node:url";

// Component unit tests for the checkout islands. The `Vendor_Module::path` import
// specifier is resolved by the engine's Vite plugins at build time; for tests we
// alias the foundation's cross-module dependencies to controllable local stubs so
// each repo tests itself in isolation (never pointing at the sibling repo): the
// customer-data bridge feeds the mini-cart its `cart` section, useCart records the
// qty/remove calls it delegates, and the shared Drawer is a slot passthrough. The
// real POST/section-reload behaviour is covered in module-storefront's tests.
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            "MageObsidian_ModernFrontend::js/customer-data": fileURLToPath(
                new URL("./src/Test/Js/stubs/customerData.ts", import.meta.url),
            ),
            "MageObsidian_Storefront::js/useCart": fileURLToPath(
                new URL("./src/Test/Js/stubs/useCart.ts", import.meta.url),
            ),
            "MageObsidian_Storefront::elements/Drawer": fileURLToPath(
                new URL("./src/Test/Js/stubs/Drawer.vue", import.meta.url),
            ),
            "MageObsidian_Storefront::js/form-key-provider": fileURLToPath(
                new URL("./src/Test/Js/stubs/form-key-provider.ts", import.meta.url),
            ),
            // The shared-Pinia enabler is engine glue; stub it so store tests run
            // against a plain test Pinia. The checkout store/api are the real files.
            "MageObsidian_ModernFrontend::js/store": fileURLToPath(
                new URL("./src/Test/Js/stubs/store.ts", import.meta.url),
            ),
            "MageObsidian_Checkout::js/useCheckout": fileURLToPath(
                new URL("./src/view/frontend/web/js/useCheckout.ts", import.meta.url),
            ),
            // The checkout REST wrapper is the real file (the store drives it
            // through a mocked fetch); the foundation's address helper + form are
            // stubbed so this repo tests itself without the sibling.
            "MageObsidian_Checkout::js/useCheckoutApi": fileURLToPath(
                new URL("./src/view/frontend/web/js/useCheckoutApi.ts", import.meta.url),
            ),
            "MageObsidian_Checkout::js/useShippingEstimator": fileURLToPath(
                new URL("./src/view/frontend/web/js/useShippingEstimator.ts", import.meta.url),
            ),
            "MageObsidian_Storefront::js/address": fileURLToPath(
                new URL("./src/Test/Js/stubs/address.ts", import.meta.url),
            ),
            "MageObsidian_Storefront::form/AddressForm": fileURLToPath(
                new URL("./src/Test/Js/stubs/AddressForm.vue", import.meta.url),
            ),
            "MageObsidian_Checkout::checkout/IdentificationStep": fileURLToPath(
                new URL("./src/view/frontend/web/components/checkout/IdentificationStep.vue", import.meta.url),
            ),
            "MageObsidian_Checkout::checkout/ShippingStep": fileURLToPath(
                new URL("./src/view/frontend/web/components/checkout/ShippingStep.vue", import.meta.url),
            ),
            "MageObsidian_Checkout::checkout/PaymentStep": fileURLToPath(
                new URL("./src/view/frontend/web/components/checkout/PaymentStep.vue", import.meta.url),
            ),
            "MageObsidian_Checkout::checkout/ReviewStep": fileURLToPath(
                new URL("./src/view/frontend/web/components/checkout/ReviewStep.vue", import.meta.url),
            ),
            "MageObsidian_Checkout::checkout/OnePageCheckout": fileURLToPath(
                new URL("./src/view/frontend/web/components/checkout/OnePageCheckout.vue", import.meta.url),
            ),
        },
    },
    test: {
        environment: "happy-dom",
        globals: true,
        include: ["src/view/frontend/web/**/*.test.{js,ts}"],
    },
});
