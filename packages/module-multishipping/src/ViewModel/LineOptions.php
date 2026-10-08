<?php
/**
 * This file is part of the MageObsidian - Multishipping project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Multishipping\ViewModel;

use Magento\Catalog\Helper\Product\ConfigurationPool;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Quote\Model\Quote\Address\Item as AddressItem;
use Throwable;

class LineOptions implements ArgumentInterface
{
    public function __construct(private readonly ConfigurationPool $configurationPool)
    {
    }

    public function getOptions(object $item): array
    {
        $quoteItem = $item instanceof AddressItem && $item->getQuoteItem() !== null ? $item->getQuoteItem() : $item;

        try {
            $options = $this->configurationPool
                ->getByProductType((string)$quoteItem->getProductType())
                ->getOptions($quoteItem);
        } catch (Throwable) {
            return [];
        }

        $lines = [];
        foreach ($options as $option) {
            $value = $option['value'] ?? '';
            if (is_array($value)) {
                $value = implode(', ', array_map(static fn($part): string => trim(strip_tags((string)$part)), $value));
            }
            $lines[] = [
                'label' => (string)($option['label'] ?? ''),
                'value' => trim(strip_tags((string)$value)),
            ];
        }

        return $lines;
    }
}
