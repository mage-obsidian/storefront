<?php
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Catalog\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class RecentlyProducts implements ArgumentInterface
{
    public const string SCOPE_PATH = 'catalog/recently_products/scope';

    public const string SYNC_PATH = 'catalog/recently_products/synchronize_with_backend';

    public const string VIEWED_LIFETIME_PATH = 'catalog/recently_products/recently_viewed_lifetime';

    public const string COMPARED_LIFETIME_PATH = 'catalog/recently_products/recently_compared_lifetime';

    public const string CARDS_ROUTE = 'mageobsidian-catalog/recent/cards';

    public const string SYNC_ROUTE = 'catalog/product/frontend_action_synchronize';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $urlBuilder,
        private readonly Registry $registry
    ) {
    }

    public function config(): array
    {
        return [
            'scope' => $this->scopeKey(),
            'sync' => $this->scopeConfig->isSetFlag(self::SYNC_PATH, ScopeInterface::SCOPE_STORE),
            'syncUrl' => $this->urlBuilder->getUrl(self::SYNC_ROUTE),
            'viewedLifetime' => $this->lifetime(self::VIEWED_LIFETIME_PATH),
            'comparedLifetime' => $this->lifetime(self::COMPARED_LIFETIME_PATH),
        ];
    }

    public function cardsUrl(): string
    {
        return $this->urlBuilder->getUrl(self::CARDS_ROUTE);
    }

    public function currentProductId(): ?string
    {
        $product = $this->registry->registry('current_product');

        return $product instanceof Product && $product->getId() ? (string)$product->getId() : null;
    }

    private function scopeKey(): string
    {
        $scope = (string)$this->scopeConfig->getValue(self::SCOPE_PATH, ScopeInterface::SCOPE_WEBSITE);
        $store = $this->storeManager->getStore();

        return match ($scope) {
            'store' => 'store-' . $store->getId(),
            'group' => 'group-' . $store->getStoreGroupId(),
            default => 'website-' . $store->getWebsiteId(),
        };
    }

    private function lifetime(string $path): int
    {
        $value = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);

        return is_numeric($value) && (int)$value >= 0 ? (int)$value : 1000;
    }
}
