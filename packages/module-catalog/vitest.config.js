import { defineConfig } from "vitest/config";
import vue from "@vitejs/plugin-vue";
import { fileURLToPath } from "node:url";

// Component unit tests for the catalog islands. The `Vendor_Module::path` import
// specifier is resolved by the engine's Vite plugins at runtime; for tests we
// alias the storefront's useCart composable (the configurable island's only
// cross-module dependency) to a controllable stub so we assert the island's own
// behaviour — building super_attribute and delegating to the cart — without the
// live Magento session quote. The cart POST itself is covered in module-storefront.
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            "MageObsidian_Storefront::js/useCart": fileURLToPath(
                new URL("./src/Test/Js/stubs/useCart.js", import.meta.url),
            ),
        },
    },
    test: {
        environment: "happy-dom",
        globals: true,
        include: ["src/view/frontend/web/**/*.test.{js,ts}"],
    },
});
