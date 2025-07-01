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
                new URL("./src/Test/Js/stubs/customerData.js", import.meta.url),
            ),
            "MageObsidian_Storefront::js/useCart": fileURLToPath(
                new URL("./src/Test/Js/stubs/useCart.js", import.meta.url),
            ),
            "MageObsidian_Storefront::elements/Drawer": fileURLToPath(
                new URL("./src/Test/Js/stubs/Drawer.vue", import.meta.url),
            ),
            "MageObsidian_Storefront::js/form-key-provider": fileURLToPath(
                new URL("./src/Test/Js/stubs/form-key-provider.js", import.meta.url),
            ),
        },
    },
    test: {
        environment: "happy-dom",
        globals: true,
        include: ["src/view/frontend/web/**/*.test.{js,ts}"],
    },
});
