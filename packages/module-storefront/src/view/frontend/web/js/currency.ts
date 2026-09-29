// This file is part of the MageObsidian - Storefront project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
export const DEFAULT_CURRENCY_FORMAT = "%s";

export function formatCurrency(
    format: string | null | undefined,
    amount: number | string | null | undefined,
): string {
    return (format || DEFAULT_CURRENCY_FORMAT).replace("%s", Number(amount ?? 0).toFixed(2));
}
