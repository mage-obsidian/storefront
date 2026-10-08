<?php
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Catalog\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\CatalogSearch\Block\SearchTermsLog;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Throwable;

class ReportBeacons implements ArgumentInterface
{
    private const string REPORTS_ENABLED = 'reports/options/enabled';

    private const string PRODUCT_VIEW_ENABLED = 'reports/options/product_view_enabled';

    public function __construct(
        private readonly Registry $registry,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlInterface $urlBuilder,
        private readonly SearchTermsLog $searchTermsLog
    ) {
    }

    public function productView(): ?array
    {
        if (!$this->isEnabled(self::REPORTS_ENABLED) || !$this->isEnabled(self::PRODUCT_VIEW_ENABLED)) {
            return null;
        }

        $product = $this->registry->registry('current_product');
        if (!$product instanceof Product || !$product->getId()) {
            return null;
        }

        return [
            'url' => $this->urlBuilder->getUrl('reports/report_product/view'),
            'productId' => (string)$product->getId(),
        ];
    }

    public function searchTerms(): ?array
    {
        try {
            if (!$this->searchTermsLog->isPageCacheable()) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        return ['url' => $this->urlBuilder->getUrl('catalogsearch/searchTermsLog/save')];
    }

    private function isEnabled(string $path): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE);
    }
}
