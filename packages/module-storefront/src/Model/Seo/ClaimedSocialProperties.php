<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\Model\Seo;

use Magento\Framework\View\LayoutInterface;
use Throwable;

class ClaimedSocialProperties
{
    /**
     * @param array<string, string> $claimants
     */
    public function __construct(
        private readonly LayoutInterface $layout,
        private readonly array $claimants = []
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function get(): array
    {
        $claimed = [];
        foreach ($this->claimants as $blockName => $dataKey) {
            foreach ($this->propertiesOf((string)$blockName, (string)$dataKey) as $property) {
                $claimed[$property] = true;
            }
        }

        return array_keys($claimed);
    }

    /**
     * @return array<int, string>
     */
    private function propertiesOf(string $blockName, string $dataKey): array
    {
        if ($blockName === '' || $dataKey === '') {
            return [];
        }

        try {
            $block = $this->layout->getBlock($blockName);
            if ($block === false) {
                return [];
            }
            $source = $block->getData($dataKey);
            if (!is_object($source) || !method_exists($source, 'getProperties')) {
                return [];
            }
            $properties = $source->getProperties();
        } catch (Throwable) {
            return [];
        }

        return is_array($properties) ? array_keys($properties) : [];
    }
}
