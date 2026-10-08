// This file is part of the MageObsidian - Storefront project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
for (const form of document.querySelectorAll<HTMLFormElement>('form[data-auto-submit]')) {
    form.requestSubmit();
}
