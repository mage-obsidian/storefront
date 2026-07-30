import { defineConfig } from "vitest/config";
import vue from "@vitejs/plugin-vue";
import { fileURLToPath } from "node:url";

// Unit tests for the guest orders & returns island. happy-dom drives the SFC's
// reactive toggle; @vitejs/plugin-vue compiles it. The `Vendor_Module::path`
// specifiers the engine resolves at build time are aliased here to the real
// sources they name.
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            "MageObsidian_Storefront::form/Field": fileURLToPath(
                new URL("../module-storefront/src/view/frontend/web/components/form/Field.vue", import.meta.url),
            ),
            "MageObsidian_Storefront::js/form-key-provider": fileURLToPath(
                new URL("./src/Test/Js/stubs/form-key-provider.ts", import.meta.url),
            ),
        },
    },
    test: {
        environment: "happy-dom",
        globals: true,
        include: ["src/view/frontend/web/**/*.test.{js,ts}"],
    },
});
