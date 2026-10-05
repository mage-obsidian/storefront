<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

class NavigationContinuity implements ArgumentInterface
{
    public const string CONFIG_RETAIN = 'mage_obsidian/navigation/retain';

    private const string MARKER_ID = 'obsidian-main-end';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::CONFIG_RETAIN, ScopeInterface::SCOPE_STORE);
    }

    public function getMarkerId(): string
    {
        return self::MARKER_ID;
    }
}
