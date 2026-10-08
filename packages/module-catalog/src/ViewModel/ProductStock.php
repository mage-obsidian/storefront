<?php
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Catalog\ViewModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Throwable;

class ProductStock implements ArgumentInterface
{
    public const string DISPLAY_STATUS_PATH = 'cataloginventory/options/display_product_stock_status';
    public const string THRESHOLD_PATH = 'cataloginventory/options/stock_threshold_qty';

    public function __construct(
        private readonly Registry $registry,
        private readonly StockRegistryInterface $stockRegistry,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getAvailability(): ?array
    {
        $product = $this->registry->registry('current_product');
        if (!$product instanceof ProductInterface || !$this->displaysAvailability()) {
            return null;
        }

        $available = (bool)$product->isAvailable();

        return ['inStock' => $available, 'left' => $available ? $this->quantityLeft($product) : null];
    }

    private function displaysAvailability(): bool
    {
        return (bool)(int)$this->scopeConfig->getValue(self::DISPLAY_STATUS_PATH, ScopeInterface::SCOPE_STORE);
    }

    public function hasIncrements(): bool
    {
        return $this->readIncrements() > 0.0;
    }

    public function getIncrements(): int|float
    {
        return $this->whole($this->readIncrements());
    }

    public function getStep(): int|float
    {
        $increments = $this->readIncrements();

        return $this->whole($increments > 0.0 ? $increments : 1.0);
    }

    public function getMinQty(): int|float
    {
        $increments = $this->readIncrements();
        $minimum = $this->readMinSaleQty();
        if ($minimum <= 0.0) {
            $minimum = 1.0;
        }
        if ($increments > 0.0) {
            $minimum = ceil($minimum / $increments) * $increments;
        }

        return $this->whole($minimum);
    }

    private function quantityLeft(ProductInterface $product): int|float|null
    {
        $threshold = (float)$this->scopeConfig->getValue(self::THRESHOLD_PATH, ScopeInterface::SCOPE_STORE);
        $stockItem = $this->stockItem();
        if ($threshold <= 0.0 || $stockItem === null) {
            return null;
        }

        try {
            $websiteId = $product->getStore()->getWebsiteId();
            $quantity = (float)$this->stockRegistry->getStockStatus($product->getId(), $websiteId)->getQty();
        } catch (Throwable) {
            return null;
        }

        $left = $quantity - (float)$stockItem->getMinQty();

        return $quantity > 0.0 && $left > 0.0 && $left <= $threshold ? $this->whole($left) : null;
    }

    private function readIncrements(): float
    {
        $stockItem = $this->stockItem();

        return $stockItem === null ? 0.0 : (float)$stockItem->getQtyIncrements();
    }

    private function readMinSaleQty(): float
    {
        $stockItem = $this->stockItem();

        return $stockItem === null ? 1.0 : (float)$stockItem->getMinSaleQty();
    }

    private function stockItem(): ?\Magento\CatalogInventory\Api\Data\StockItemInterface
    {
        $product = $this->registry->registry('current_product');
        if (!$product instanceof ProductInterface) {
            return null;
        }

        try {
            return $this->stockRegistry->getStockItem($product->getId(), $product->getStore()->getWebsiteId());
        } catch (Throwable) {
            return null;
        }
    }

    private function whole(float $value): int|float
    {
        return floor($value) === $value ? (int)$value : $value;
    }
}
