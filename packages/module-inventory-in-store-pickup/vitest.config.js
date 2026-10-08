// This file is part of the MageObsidian - InventoryInStorePickup project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { defineConfig } from "vitest/config";
import vue from "@vitejs/plugin-vue";
import { fileURLToPath } from "node:url";

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            "MageObsidian_Storefront::js/address": fileURLToPath(
                new URL("../module-storefront/src/view/frontend/web/js/address.ts", import.meta.url),
            ),
            "MageObsidian_InventoryInStorePickup::js/pickup-locations": fileURLToPath(
                new URL("./src/view/frontend/web/js/pickup-locations.ts", import.meta.url),
            ),
        },
    },
    test: {
        environment: "happy-dom",
        globals: true,
        include: ["src/view/frontend/web/**/*.test.{js,ts}"],
    },
});
