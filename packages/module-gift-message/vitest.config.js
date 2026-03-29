import { defineConfig } from "vitest/config";
import vue from "@vitejs/plugin-vue";
import { fileURLToPath } from "node:url";

// Unit tests for the gift-message island. The `Vendor_Module::path` specifier is
// resolved by the engine's Vite plugins at build time; for tests we alias the
// module's own imports to their source files so the repo tests itself in
// isolation. The REST composable is the real file (driven through a mocked fetch).
export default defineConfig({
    plugins: [vue()],
    test: {
        environment: "happy-dom",
    },
    resolve: {
        alias: {
            "MageObsidian_GiftMessage::js/useGiftMessage": fileURLToPath(
                new URL("./src/view/frontend/web/js/useGiftMessage.ts", import.meta.url),
            ),
            "MageObsidian_GiftMessage::components/gift/GiftMessageForm": fileURLToPath(
                new URL("./src/view/frontend/web/components/gift/GiftMessageForm.vue", import.meta.url),
            ),
        },
    },
});
