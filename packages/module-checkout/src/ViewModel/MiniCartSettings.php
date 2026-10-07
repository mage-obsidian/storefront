<?php
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Checkout\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

class MiniCartSettings implements ArgumentInterface
{
    private const DISPLAY_PATH = 'checkout/sidebar/display';
    private const MAX_ITEMS_PATH = 'checkout/sidebar/max_items_display_count';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::DISPLAY_PATH, ScopeInterface::SCOPE_STORE);
    }

    public function getMaxItems(): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::MAX_ITEMS_PATH, ScopeInterface::SCOPE_STORE));
    }
}
