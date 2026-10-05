<?php
/**
 * This file is part of the MageObsidian - Search project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

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
