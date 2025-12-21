<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

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
     * Memoised rows: id, name, url, image, qty, price, rowTotal, options[].
     *
     * @var list<array<string, mixed>>|null
     */
    private ?array $rows = null;

    /**
     * @param CheckoutSession $checkoutSession
     * @param ConfigurationPool $configurationPool
     * @param ImageHelper $imageHelper
     * @param PriceCurrencyInterface $priceCurrency
     * @param UrlInterface $url
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly ConfigurationPool $configurationPool,
        private readonly ImageHelper $imageHelper,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly UrlInterface $url
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
        // Prefer incl-tax row total when the store collected it; both are already
        // store-currency floats, so PriceCurrency just formats them.
        $rowTotal = $item->getRowTotalInclTax() ?: $item->getRowTotal();

        return [
            'id' => (int)$item->getItemId(),
            'name' => (string)$item->getName(),
            'url' => $product ? (string)$product->getProductUrl() : '',
            'image' => $this->thumbnail($item),
            'qty' => (float)$item->getQty(),
            'price' => (string)$this->priceCurrency->format((float)$item->getCalculationPrice(), false),
            'rowTotal' => (string)$this->priceCurrency->format((float)$rowTotal, false),
            'options' => $this->options($item),
            'configureUrl' => $this->configureUrl($item),
        ];
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
}
