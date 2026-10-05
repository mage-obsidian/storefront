// This file is part of the MageObsidian - Storefront project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
import { existsSync } from "node:fs";
import { resolve } from "node:path";
import { fileURLToPath } from "node:url";

const storefrontRoot = fileURLToPath(new URL("../..", import.meta.url));

export const frameworkRoot = resolve(storefrontRoot, process.env.MAGE_OBSIDIAN_FRAMEWORK_DIR ?? "../framework");

export const frameworkPath = (relative) => {
    if (!existsSync(resolve(frameworkRoot, "packages/js-package-utils"))) {
        throw new Error(
            `No MageObsidian framework checkout at ${frameworkRoot}. Clone mage-obsidian/framework next to this repository or set MAGE_OBSIDIAN_FRAMEWORK_DIR.`,
        );
    }
    return resolve(frameworkRoot, relative);
};
