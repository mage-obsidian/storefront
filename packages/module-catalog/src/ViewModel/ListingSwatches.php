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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Swatches\Block\Product\Renderer\Listing\Configurable;
use MageObsidian\Catalog\Service\Product\ListingSwatchState;
use Throwable;

class ListingSwatches implements ArgumentInterface
{
    private const string IMAGE_ID = 'category_page_grid';

    private const int DEFAULT_LIMIT = 16;

    public function __construct(
        private readonly ListingSwatchState $state,
        private readonly ProductCard $card,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag('catalog/frontend/show_swatches_in_product_list', ScopeInterface::SCOPE_STORE);
    }

    public function showsTooltip(): bool
    {
        return $this->scopeConfig->isSetFlag('catalog/frontend/show_swatch_tooltip', ScopeInterface::SCOPE_STORE);
    }

    public function groups(Configurable $renderer): array
    {
        return $this->view($renderer)['groups'];
    }

    public function view(Configurable $renderer): array
    {
        try {
            $product = $renderer->getProduct();
            $limit = (int)$renderer->getNumberSwatchesPerProduct();
            $config = (string)$renderer->getJsonConfig();

            $groups = $this->state->build(
                $config,
                (string)$renderer->getJsonSwatchConfig(),
                fn(string $variantId): ?array => $this->variantImage($product, $variantId),
                $limit > 0 ? $limit : self::DEFAULT_LIMIT
            );

            return [
                'groups' => $groups,
                'purchase' => $this->state->purchase(
                    $config,
                    $groups,
                    fn(float $amount): string => $this->priceCurrency->format($amount, false)
                ),
                'post' => $this->card->getAddToCartPostParams($product),
            ];
        } catch (Throwable) {
            return ['groups' => [], 'purchase' => [], 'post' => []];
        }
    }

    private function variantImage(Product $product, string $variantId): ?array
    {
        foreach ($product->getTypeInstance()->getUsedProducts($product) as $variant) {
            if ((string)$variant->getId() !== $variantId) {
                continue;
            }
            $file = $variant->getData('small_image');
            if ($file === null || $file === '' || $file === 'no_selection') {
                return null;
            }
            $image = $this->card->getImage($variant, self::IMAGE_ID);

            return ['src' => $image['src'], 'srcset' => $image['srcset']];
        }

        return null;
    }
}
