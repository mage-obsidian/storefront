<?php
/**
 * This file is part of the MageObsidian - Search project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Search\Model\Fragment;

enum RequestParameter: string
{
    /** Asks a listing page for its fragments instead of the full page. */
    case Fragment = 'obsidian_fragment';

    /** Per-visit opt-out: carried on the page URL, keeps the enhancer off. */
    case OptOut = 'obsidian_data';

    public const string VALUE_ON = '1';
    public const string VALUE_OFF = '0';
}
