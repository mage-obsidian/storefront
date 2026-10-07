<?php
/**
 * This file is part of the MageObsidian - Customer project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace MageObsidian\Customer\ViewModel;

use Magento\Customer\Model\AccountManagement;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

class PasswordPolicy implements ArgumentInterface
{
    private const DEFAULT_MINIMUM_LENGTH = 8;
    private const DEFAULT_CLASSES = 3;
    private const KNOWN_CLASSES = 4;

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function minLength(): int
    {
        $length = (int)$this->scopeConfig->getValue(
            AccountManagement::XML_PATH_MINIMUM_PASSWORD_LENGTH,
            ScopeInterface::SCOPE_STORE
        );

        return $length >= 1 ? $length : self::DEFAULT_MINIMUM_LENGTH;
    }

    public function requiredClasses(): int
    {
        $configured = $this->scopeConfig->getValue(
            AccountManagement::XML_PATH_REQUIRED_CHARACTER_CLASSES_NUMBER,
            ScopeInterface::SCOPE_STORE
        );

        if ($configured === null || $configured === '') {
            return self::DEFAULT_CLASSES;
        }

        return max(1, min(self::KNOWN_CLASSES, (int)$configured));
    }
}
