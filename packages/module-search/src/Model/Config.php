<?php
/**
 * This file is part of the MageObsidian - Search project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Search\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const string XML_PATH_FRAGMENTS_ENABLED = 'mage_obsidian/listing/fragments_enabled';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function areFragmentsEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_FRAGMENTS_ENABLED, ScopeInterface::SCOPE_STORE);
    }
}
