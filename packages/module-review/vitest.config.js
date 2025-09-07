import { defineConfig } from "vitest/config";

// Unit tests for the review form enhancer (plain TS, no Vue / cross-module
// imports), so no plugins or aliases are needed.
export default defineConfig({
    test: {
        environment: "happy-dom",
        globals: true,
        include: ["src/view/frontend/web/**/*.test.{js,ts}"],
    },
});
