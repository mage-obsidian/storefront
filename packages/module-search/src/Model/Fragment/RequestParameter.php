<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Search project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

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
