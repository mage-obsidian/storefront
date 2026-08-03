<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Search project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Search\Exception;

use Magento\Framework\Exception\LocalizedException;

/**
 * A listing could not be served as fragments, so the whole request falls back
 * to the native page. Never partial: a half-served listing is a worse outcome
 * than a slower one.
 */
class FragmentUnavailableException extends LocalizedException
{
}
