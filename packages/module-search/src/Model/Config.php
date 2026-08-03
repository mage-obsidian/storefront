<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Search project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

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
