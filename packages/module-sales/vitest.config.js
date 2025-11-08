import { defineConfig } from "vitest/config";
import vue from "@vitejs/plugin-vue";

// Unit tests for the guest orders & returns island. happy-dom drives the SFC's
// reactive toggle; @vitejs/plugin-vue compiles it. The island imports only from
// "vue" (no Vendor_Module:: specifiers), so no resolve aliases are needed.
export default defineConfig({
    plugins: [vue()],
    test: {
        environment: "happy-dom",
        globals: true,
        include: ["src/view/frontend/web/**/*.test.{js,ts}"],
    },
});
