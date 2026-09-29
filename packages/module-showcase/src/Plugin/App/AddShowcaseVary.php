<?php
/**
 * This file is part of the MageObsidian - Showcase project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Showcase\Plugin\App;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use MageObsidian\Showcase\Model\Profile;

/**
 * Puts the visitor's profile into the vary, the same way core varies a page by
 * customer group. Without it a page cached for one profile would be served to
 * the next visitor, which on a demo means showing the feature switched off to
 * someone who just switched it on.
 */
class AddShowcaseVary
{
    public const string CONTEXT_KEY = 'mage_obsidian_showcase';

    public const string DEFAULT_SIGNATURE = '';

    public function __construct(
        private readonly HttpContext $httpContext,
        private readonly Profile $profile
    ) {
    }

    public function beforeExecute(ActionInterface $subject): void
    {
        $this->httpContext->setValue(
            self::CONTEXT_KEY,
            $this->profile->signature(),
            self::DEFAULT_SIGNATURE
        );
    }
}
