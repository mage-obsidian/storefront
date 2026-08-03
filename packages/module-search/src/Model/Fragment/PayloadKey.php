<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Search project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Search\Model\Fragment;

/**
 * The wire format between the fragment endpoint and the listing navigator.
 */
enum PayloadKey: string
{
    case Sections = 'sections';
    case Error = 'error';
}
