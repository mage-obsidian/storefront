import { defineConfig } from "vitest/config";
import { fileURLToPath } from "node:url";

// Unit tests for the review form enhancer. The `Vendor_Module::path` specifier
// is resolved by the engine's Vite plugins at build time; here the shared
// validation engine is aliased to its real source in module-storefront.
export default defineConfig({
    resolve: {
        alias: {
            "MageObsidian_Storefront::js/form-validation": fileURLToPath(
                new URL("../module-storefront/src/view/frontend/web/js/form-validation.ts", import.meta.url),
            ),
        },
    },
    test: {
        environment: "happy-dom",
        globals: true,
        include: ["src/view/frontend/web/**/*.test.{js,ts}"],
    },
});
