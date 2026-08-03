import { defineConfig } from "vitest/config";
import { fileURLToPath } from "node:url";

// The `Vendor_Module::path` specifier is resolved by the engine's Vite plugins
// at build time; here the panel is pointed at its real source. It imports
// nothing else, which is why this is the only alias.
export default defineConfig({
    resolve: {
        alias: {
            "MageObsidian_Showcase::js/showcase-panel": fileURLToPath(
                new URL("./src/view/frontend/web/js/showcase-panel.ts", import.meta.url),
            ),
        },
    },
    test: {
        environment: "happy-dom",
        globals: true,
        include: ["src/view/frontend/web/**/*.test.{js,ts}"],
    },
});
