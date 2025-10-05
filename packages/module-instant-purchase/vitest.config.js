import { defineConfig } from "vitest/config";
import vue from "@vitejs/plugin-vue";
import { fileURLToPath } from "node:url";

// Unit tests for the instant-purchase island. The customer-data bridge is stubbed
// (the composable is handed an explicit store anyway); the composable under test is
// the real file. There is no tokenizing gateway in this environment, so the live
// charge path (the place-order controller) is NOT exercised here.
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            "MageObsidian_ModernFrontend::js/customer-data": fileURLToPath(
                new URL("./src/Test/Js/stubs/customerData.ts", import.meta.url),
            ),
            "MageObsidian_InstantPurchase::js/useInstantPurchase": fileURLToPath(
                new URL("./src/view/frontend/web/js/useInstantPurchase.ts", import.meta.url),
            ),
        },
    },
    test: {
        environment: "happy-dom",
        globals: true,
        include: ["src/view/frontend/web/**/*.test.{js,ts}"],
    },
});
