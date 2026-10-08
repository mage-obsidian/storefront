<?php
/**
 * This file is part of the MageObsidian - Review project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Review\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Review\Service\RatingSummaryReader;

class ListRatings implements ArgumentInterface
{
    /** @var array<int, array{percent: int, count: int}>|null */
    private ?array $summaries = null;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param RatingSummaryReader $reader
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly RatingSummaryReader $reader
    ) {
    }

    /**
     * @param object $product
     * @param iterable<object> $siblings Every product the listing renders, so one read serves all cards.
     * @return array{percent: int, count: int}|null
     */
    public function getSummary(object $product, iterable $siblings): ?array
    {
        if (!$this->scopeConfig->isSetFlag('catalog/review/active', ScopeInterface::SCOPE_STORE)) {
            return null;
        }

        if ($this->summaries === null) {
            $ids = [];
            foreach ($siblings as $sibling) {
                $ids[] = (int)$sibling->getId();
            }
            $this->summaries = $this->reader->read($ids, (int)$this->storeManager->getStore()->getId());
        }

        $summary = $this->summaries[(int)$product->getId()] ?? null;

        return $summary !== null && $summary['count'] > 0 ? $summary : null;
    }
}
