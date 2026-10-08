<?php
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Checkout\ViewModel;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Helper\Product\ConfigurationPool;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Throwable;

/**
 * Line data for the shopping bag page.
 *
 * MageObsidian suppresses the core checkout layout, so the native cart item
 * renderers never run. This ViewModel normalises the session quote's visible
 * items into the rows the Twig table reads — thumbnail, options (via the same
 * ConfigurationPool the mini-cart customer-data uses, so configurable swatches /
 * custom options render identically), unit price and row total. Any failure
 * degrades to an empty list so the page still renders.
 */
class CartItems implements ArgumentInterface
{
    /**
     * Image id (see theme view.xml) for the line thumbnail artwork.
     */
    private const THUMBNAIL_IMAGE_ID = 'cart_page_product_thumbnail';

    /**
     * Memoised rows: id, name, url, image, qty, price, rowTotal, prices, rowTotals, fixedTaxes[], options[], messages[].
     *
     * @var list<array<string, mixed>>|null
     */
    private ?array $rows = null;

    private ?string $continueShoppingUrl = null;

    /**
     * @param CheckoutSession $checkoutSession
     * @param ConfigurationPool $configurationPool
     * @param ImageHelper $imageHelper
     * @param PriceCurrencyInterface $priceCurrency
     * @param UrlInterface $url
     * @param TaxDisplay $taxDisplay
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly ConfigurationPool $configurationPool,
        private readonly ImageHelper $imageHelper,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly UrlInterface $url,
        private readonly TaxDisplay $taxDisplay
    ) {
    }

    /**
     * Visible quote items as render-ready rows (memoised).
     *
     * @return list<array<string, mixed>>
     */
    public function getItems(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }
        try {
            $this->rows = array_map(
                fn (QuoteItem $item): array => $this->buildRow($item),
                $this->checkoutSession->getQuote()->getAllVisibleItems()
            );
        } catch (Throwable) {
            $this->rows = [];
        }
        return $this->rows;
    }

    /**
     * Map one quote item to a render-ready row.
     *
     * @param QuoteItem $item
     * @return array<string, mixed>
     */
    private function buildRow(QuoteItem $item): array
    {
        $product = $item->getProduct();
        $amounts = $this->amounts($item);
        $mode = $this->taxDisplay->priceMode();
        $prices = $this->taxDisplay->pick($mode, $amounts['unitIncl'], $amounts['unitExcl']);
        $rowTotals = $this->taxDisplay->pick($mode, $amounts['rowIncl'], $amounts['rowExcl']);
        $primary = $mode === TaxDisplay::INCL ? 'incl' : 'excl';

        return [
            'id' => (int)$item->getItemId(),
            'name' => (string)$item->getName(),
            'sku' => (string)$item->getSku(),
            'url' => $product ? (string)$product->getProductUrl() : '',
            'image' => $this->thumbnail($item),
            'qty' => (float)$item->getQty(),
            'price' => $this->money($prices[$primary]),
            'rowTotal' => $this->money($rowTotals[$primary]),
            'prices' => array_map($this->money(...), array_filter($prices, static fn (?float $amount): bool => $amount !== null)),
            'rowTotals' => array_map($this->money(...), array_filter($rowTotals, static fn (?float $amount): bool => $amount !== null)),
            'fixedTaxes' => $this->fixedTaxes($item),
            'options' => $this->options($item),
            'configureUrl' => $this->configureUrl($item),
            'messages' => $this->messages($item),
        ];
    }

    private function money(?float $amount): string
    {
        return (string)$this->priceCurrency->format((float)$amount, false);
    }

    /**
     * @return array{unitExcl: float, unitIncl: float, rowExcl: float, rowIncl: float}
     */
    private function amounts(QuoteItem $item): array
    {
        $qty = (float)$item->getQty();
        $rowExcl = (float)$item->getRowTotal();
        if ($rowExcl <= 0 && $qty > 0) {
            $rowExcl = (float)$item->getCalculationPrice() * $qty;
        }
        $unitExcl = (float)$item->getCalculationPrice();
        $rowIncl = (float)$item->getRowTotalInclTax() ?: $rowExcl;
        $unitIncl = (float)$item->getPriceInclTax() ?: $unitExcl;

        if ($this->taxDisplay->includesFixedTaxInPrice()) {
            $fixed = $this->taxDisplay->itemFixedTaxAmounts($item);
            $rowExcl += $fixed['excl'];
            $rowIncl += $fixed['incl'];
            if ($qty > 0) {
                $unitExcl = $rowExcl / $qty;
                $unitIncl = $rowIncl / $qty;
            }
        }

        return ['unitExcl' => $unitExcl, 'unitIncl' => $unitIncl, 'rowExcl' => $rowExcl, 'rowIncl' => $rowIncl];
    }

    /**
     * @return list<array{label: string, amount: string}>
     */
    private function fixedTaxes(QuoteItem $item): array
    {
        if (!in_array($this->taxDisplay->fixedTaxMode(), ['incl_desc', 'excl_desc_incl'], true)) {
            return [];
        }

        return array_map(
            fn (array $fixedTax): array => [
                'label' => $fixedTax['label'],
                'amount' => $this->money(
                    $this->taxDisplay->priceMode() === TaxDisplay::INCL ? $fixedTax['amountInclTax'] : $fixedTax['amount']
                ),
            ],
            array_values(array_filter(
                $this->taxDisplay->itemFixedTaxes($item),
                static fn (array $fixedTax): bool => $fixedTax['amount'] > 0
            ))
        );
    }

    private function messages(QuoteItem $item): array
    {
        return array_values(array_filter(
            array_map('strval', (array)$item->getMessage(false)),
            static fn (string $text): bool => $text !== ''
        ));
    }

    /**
     * Reconfigure url for an item that has options to change, empty otherwise.
     *
     * @param QuoteItem $item
     * @return string
     */
    private function configureUrl(QuoteItem $item): string
    {
        $product = $item->getProduct();
        if (!$product) {
            return '';
        }
        try {
            if (!$product->getTypeInstance()->canConfigure($product)) {
                return '';
            }

            return (string)$this->url->getUrl(
                'checkout/cart/configure',
                ['id' => (int)$item->getItemId(), 'product_id' => (int)$product->getId()]
            );
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Selected options for a line (configurable swatches, custom options).
     *
     * @param QuoteItem $item
     * @return array<int, array{label: string, value: string}>
     */
    private function options(QuoteItem $item): array
    {
        try {
            $options = $this->configurationPool
                ->getByProductType($item->getProductType())
                ->getOptions($item);
        } catch (Throwable) {
            return [];
        }

        $normalised = [];
        foreach ($options as $option) {
            $value = $option['value'] ?? '';
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $normalised[] = [
                'label' => (string)($option['label'] ?? ''),
                'value' => trim(strip_tags((string)$value)),
            ];
        }

        return $normalised;
    }

    /**
     * Line thumbnail URL (placeholder-safe, empty on failure).
     *
     * @param QuoteItem $item
     * @return string
     */
    private function thumbnail(QuoteItem $item): string
    {
        $product = $item->getProduct();
        if (!$product) {
            return '';
        }
        try {
            return (string)$this->imageHelper->init($product, self::THUMBNAIL_IMAGE_ID)->getUrl();
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Where "Continue shopping" goes: the last listing the shopper came from, as
     * Magento's own cart block resolves it, falling back to the home page.
     */
    public function getContinueShoppingUrl(): string
    {
        if ($this->continueShoppingUrl !== null) {
            return $this->continueShoppingUrl;
        }

        try {
            $url = (string)$this->checkoutSession->getContinueShoppingUrl(true);
        } catch (Throwable) {
            $url = '';
        }

        return $this->continueShoppingUrl = $url !== '' ? $url : $this->url->getUrl('');
    }
}
