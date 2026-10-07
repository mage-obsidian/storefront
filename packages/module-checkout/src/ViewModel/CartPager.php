<?php
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Checkout\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

class CartPager implements ArgumentInterface
{
    private const PER_PAGE_PATH = 'checkout/cart/number_items_to_display_pager';
    private const PAGE_PARAM = 'p';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly RequestInterface $request,
        private readonly UrlInterface $url
    ) {
    }

    public function slice(array $rows): array
    {
        $perPage = $this->perPage();

        return array_slice($rows, ($this->currentPage(count($rows), $perPage) - 1) * $perPage, $perPage);
    }

    public function getPages(int $total): array
    {
        $perPage = $this->perPage();
        $last = $this->lastPage($total, $perPage);
        if ($last < 2) {
            return [];
        }
        $current = $this->currentPage($total, $perPage);

        return array_map(
            fn (int $number): array => [
                'number' => $number,
                'current' => $number === $current,
                'url' => $this->url->getUrl('*/*/*', ['_current' => true, '_query' => [self::PAGE_PARAM => $number]]),
            ],
            range(1, $last)
        );
    }

    private function perPage(): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::PER_PAGE_PATH, ScopeInterface::SCOPE_STORE));
    }

    private function lastPage(int $total, int $perPage): int
    {
        return max(1, (int)ceil($total / $perPage));
    }

    private function currentPage(int $total, int $perPage): int
    {
        $requested = (int)$this->request->getParam(self::PAGE_PARAM);

        return min(max(1, $requested), $this->lastPage($total, $perPage));
    }
}
