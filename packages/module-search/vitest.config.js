import { defineConfig } from "vitest/config";
import { fileURLToPath } from "node:url";

// The `Vendor_Module::path` import specifier is resolved by the engine's Vite
// plugins at runtime; here the storefront's event vocabulary and the runtime are
// pointed at their real sources (the navigator's contract with them is part of
// what these tests cover) while the event manager itself is a recording stub, so
// a dispatch can be asserted without the global singleton.
export default defineConfig({
    resolve: {
        alias: {
            "mage-obsidian/runtime": fileURLToPath(
                new URL("../js-package-utils/src/runtime", import.meta.url),
            ),
            "MageObsidian_Storefront::js/listing-events": fileURLToPath(
                new URL("../module-storefront/src/view/frontend/web/js/listing-events.ts", import.meta.url),
            ),
            "MageObsidian_ModernFrontend::js/events": fileURLToPath(
                new URL("./src/Test/Js/stubs/events.ts", import.meta.url),
            ),
            // Intra-module specifier (kept as Vendor_Module::path so the
            // resolver's inheritance applies at build time) pointed at the real
            // source here.
            "MageObsidian_Search::js/listing-navigator": fileURLToPath(
                new URL("./src/view/frontend/web/js/listing-navigator.ts", import.meta.url),
            ),
            "MageObsidian_Search::js/listing-transition": fileURLToPath(
                new URL("./src/view/frontend/web/js/listing-transition.ts", import.meta.url),
            ),
        },
    },
    test: {
        environment: "happy-dom",
        globals: true,
        include: ["src/view/frontend/web/**/*.test.{js,ts}"],
    },
});
