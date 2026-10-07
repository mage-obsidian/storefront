// This file is part of the MageObsidian - Checkout project.
//
// SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
// SPDX-License-Identifier: MIT
export const announced: unknown[][] = [];
export const cookieMessages: unknown[] = [];

export const reset = (): void => {
    announced.length = 0;
    cookieMessages.length = 0;
};

export const readCookieMessages = (): unknown[] => [...cookieMessages];

export const announce = async (messages: unknown[]): Promise<void> => {
    announced.push(messages);
};
