// This file is part of the MageObsidian - Customer project.
//
// SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
export let token: string | null = null;
export const asked: string[] = [];
export const resets: string[] = [];

export const setToken = (value: string | null): void => {
    token = value;
};

export const reset = (): void => {
    token = null;
    asked.length = 0;
    resets.length = 0;
};

export const tokenFor = async (formKey: string): Promise<string | null> => {
    asked.push(formKey);
    return token;
};

export const resetToken = (formKey: string): void => {
    resets.push(formKey);
};
