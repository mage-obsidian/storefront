import { defineConfig } from "vitest/config";
import vue from "@vitejs/plugin-vue";
import { fileURLToPath } from "node:url";

// Component unit tests for the storefront's shared Vue islands. Runs in the host
// Node toolchain (like the engine suite); happy-dom supplies the DOM the islands
// drive (focus, scroll-lock, keyboard), and @vitejs/plugin-vue compiles the SFCs.
//
// The `Vendor_Module::path` import specifier is resolved by the engine's Vite
// plugins at build time; for tests we map them here. The customer-data bridge
// points at a small controllable stub so cart components run without the live
// Magento section data; the intra-module Drawer/Switcher specifiers (used by
// MobileMenu in shipped code) resolve to their real local components.
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            "mage-obsidian/runtime": fileURLToPath(
                new URL("../js-package-utils/src/runtime", import.meta.url),
            ),
            "MageObsidian_ModernFrontend::elements/Icon": fileURLToPath(
                new URL("./src/Test/Js/stubs/Icon.vue", import.meta.url),
            ),
            "MageObsidian_ModernFrontend::js/activity": fileURLToPath(
                new URL("../module-modern-frontend/src/view/frontend/web/js/activity.ts", import.meta.url),
            ),
            "MageObsidian_ModernFrontend::js/customer-data": fileURLToPath(
                new URL("./src/Test/Js/stubs/customerData.ts", import.meta.url),
            ),
            "MageObsidian_ModernFrontend::js/events": fileURLToPath(
                new URL("./src/Test/Js/stubs/events.ts", import.meta.url),
            ),
            "MageObsidian_Storefront::elements/Drawer": fileURLToPath(
                new URL("./src/view/frontend/web/components/elements/Drawer.vue", import.meta.url),
            ),
            "MageObsidian_Storefront::navigation/Switcher": fileURLToPath(
                new URL("./src/view/frontend/web/components/navigation/Switcher.vue", import.meta.url),
            ),
            "MageObsidian_Storefront::navigation/NavAccordion": fileURLToPath(
                new URL("./src/view/frontend/web/components/navigation/NavAccordion.vue", import.meta.url),
            ),
            "MageObsidian_Storefront::js/address": fileURLToPath(
                new URL("./src/view/frontend/web/js/address.ts", import.meta.url),
            ),
            "MageObsidian_Storefront::js/useCart": fileURLToPath(
                new URL("./src/view/frontend/web/js/useCart.ts", import.meta.url),
            ),
            "MageObsidian_Storefront::form/Field": fileURLToPath(
                new URL("./src/view/frontend/web/components/form/Field.vue", import.meta.url),
            ),
            "MageObsidian_Storefront::js/form-validation": fileURLToPath(
                new URL("./src/view/frontend/web/js/form-validation.ts", import.meta.url),
            ),
            "MageObsidian_Storefront::js/scroll-lock": fileURLToPath(
                new URL("./src/view/frontend/web/js/scroll-lock.ts", import.meta.url),
            ),
            "MageObsidian_Storefront::wishlist/WishlistCount": fileURLToPath(
                new URL("./src/view/frontend/web/components/wishlist/WishlistCount.vue", import.meta.url),
            ),
            "MageObsidian_Storefront::compare/CompareCount": fileURLToPath(
                new URL("./src/view/frontend/web/components/compare/CompareCount.vue", import.meta.url),
            ),
            "MageObsidian_Storefront::js/useWishlist": fileURLToPath(
                new URL("./src/view/frontend/web/js/useWishlist.ts", import.meta.url),
            ),
            "MageObsidian_Storefront::js/useCompare": fileURLToPath(
                new URL("./src/view/frontend/web/js/useCompare.ts", import.meta.url),
            ),
            "MageObsidian_Storefront::js/search-events": fileURLToPath(
                new URL("./src/view/frontend/web/js/search-events.ts", import.meta.url),
            ),
            "MageObsidian_Storefront::js/notifications": fileURLToPath(
                new URL("./src/view/frontend/web/js/notifications.ts", import.meta.url),
            ),
            "MageObsidian_Storefront::js/digitNudge": fileURLToPath(
                new URL("./src/view/frontend/web/js/digitNudge.ts", import.meta.url),
            ),
            "MageObsidian_Storefront::js/viewTransitions": fileURLToPath(
                new URL("./src/view/frontend/web/js/viewTransitions.ts", import.meta.url),
            ),
        },
    },
    test: {
        environment: "happy-dom",
        globals: true,
        include: ["src/view/frontend/web/**/*.test.{js,ts}"],
    },
});
