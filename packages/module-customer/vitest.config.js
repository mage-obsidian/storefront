import { defineConfig } from "vitest/config";
import vue from "@vitejs/plugin-vue";
import { fileURLToPath } from "node:url";

// Unit tests for the customer auth enhancers and the header account island. The
// `Vendor_Module::path` import specifiers are resolved by the engine's Vite
// plugins at build time; here they are aliased — cross-module foundations
// (customer-data, form-key-provider) point at controllable local stubs so each
// repo tests itself in isolation, and the intra-module specifiers resolve to the
// real local sources.
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            "MageObsidian_ModernFrontend::js/customer-data": fileURLToPath(
                new URL("./src/Test/Js/stubs/customerData.ts", import.meta.url),
            ),
            "MageObsidian_Storefront::js/form-key-provider": fileURLToPath(
                new URL("./src/Test/Js/stubs/form-key-provider.ts", import.meta.url),
            ),
            "MageObsidian_Customer::js/form-validation": fileURLToPath(
                new URL("./src/view/frontend/web/js/form-validation.ts", import.meta.url),
            ),
            "MageObsidian_Customer::js/useAuth": fileURLToPath(
                new URL("./src/view/frontend/web/js/useAuth.ts", import.meta.url),
            ),
        },
    },
    test: {
        environment: "happy-dom",
        globals: true,
        include: ["src/view/frontend/web/**/*.test.{js,ts}"],
    },
});
