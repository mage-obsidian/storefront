import { defineConfig } from "vitest/config";
import vue from "@vitejs/plugin-vue";
import { fileURLToPath } from "node:url";

// Component unit tests for the storefront's shared Vue islands. Runs in the host
// Node toolchain (like the engine suite); happy-dom supplies the DOM the islands
// drive (focus, scroll-lock, keyboard), and @vitejs/plugin-vue compiles the SFCs.
//
// The `Vendor_Module::path` import specifier is resolved by the engine's Vite
// plugins at build time; for tests we alias the customer-data bridge to a small
// controllable stub so cart components run without the live Magento section data.
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            "MageObsidian_ModernFrontend::js/customer-data": fileURLToPath(
                new URL("./src/Test/Js/stubs/customerData.ts", import.meta.url),
            ),
        },
    },
    test: {
        environment: "happy-dom",
        globals: true,
        include: ["src/view/frontend/web/**/*.test.{js,ts}"],
    },
});
