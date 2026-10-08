<?php
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Catalog\Model\Recent;

use Magento\Catalog\Model\Config as CatalogConfig;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Helper\Stock;

class ProductSelection
{
    public const int MAX_IDS = 20;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly CatalogConfig $catalogConfig,
        private readonly Visibility $visibility,
        private readonly StockConfigurationInterface $stockConfiguration,
        private readonly Stock $stockHelper
    ) {
    }

    public function idsFrom(string $raw): array
    {
        $ids = [];
        foreach (explode(',', $raw) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate !== '' && ctype_digit($candidate) && (int)$candidate > 0) {
                $ids[(int)$candidate] = (int)$candidate;
            }
        }

        return array_slice(array_values($ids), 0, self::MAX_IDS);
    }

    public function load(array $ids, int $count): array
    {
        if ($ids === [] || $count < 1) {
            return [];
        }

        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect($this->catalogConfig->getProductAttributes())
            ->addIdFilter($ids)
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->setVisibility($this->visibility->getVisibleInCatalogIds())
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addUrlRewrite()
            ->addStoreFilter();

        if (!$this->stockConfiguration->isShowOutOfStock()) {
            $this->stockHelper->addIsInStockFilterToCollection($collection);
        }

        $loaded = [];
        foreach ($collection->getItems() as $product) {
            $loaded[(int)$product->getId()] = $product;
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($loaded[$id])) {
                $ordered[] = $loaded[$id];
            }
        }

        return array_slice($ordered, 0, $count);
    }
}
