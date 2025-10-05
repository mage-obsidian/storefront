<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\ViewModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Output as OutputHelper;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Framework\Url\Helper\Data as UrlHelper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Throwable;

/**
 * Buy-box data for the product detail page, consumed from Twig as
 * `block.getView()`.
 *
 * MageObsidian suppresses the core catalog frontend layout, so the native
 * product-view blocks (price box, add-to-cart form, attribute list) never load.
 * This ViewModel re-exposes the few things the PDP template needs straight off
 * the current product: identity (name/sku/type), saleability and whether the
 * type needs option selection (which decides quick-add vs. the configurable
 * island), prices (regular/final for the strikethrough), the WYSIWYG-filtered
 * description, and the add-to-cart POST target. Pricing is returned as raw
 * numbers; the template formats them with the `price` filter, mirroring the card.
 */
class ProductView implements ArgumentInterface
{
    /**
     * @param Registry $registry
     * @param OutputHelper $outputHelper
     * @param UrlInterface $url
     * @param UrlHelper $urlHelper
     * @param PriceCurrencyInterface $priceCurrency
     */
    public function __construct(
        private readonly Registry $registry,
        private readonly OutputHelper $outputHelper,
        private readonly UrlInterface $url,
        private readonly UrlHelper $urlHelper,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    /**
     * The product currently being viewed, or null off a product page.
     *
     * @return ProductInterface|null
     */
    public function getProduct(): ?ProductInterface
    {
        $product = $this->registry->registry('current_product');

        return $product instanceof ProductInterface ? $product : null;
    }

    /**
     * Product name for the heading.
     *
     * @return string
     */
    public function getName(): string
    {
        $product = $this->getProduct();

        return $product ? (string)$product->getName() : '';
    }

    /**
     * Product SKU.
     *
     * @return string
     */
    public function getSku(): string
    {
        $product = $this->getProduct();

        return $product ? (string)$product->getSku() : '';
    }

    /**
     * Product type id (simple, configurable, ...).
     *
     * @return string
     */
    public function getTypeId(): string
    {
        $product = $this->getProduct();

        return $product ? (string)$product->getTypeId() : '';
    }

    /**
     * Whether the product can currently be purchased.
     *
     * @return bool
     */
    public function isSaleable(): bool
    {
        $product = $this->getProduct();

        return $product ? (bool)$product->isSaleable() : false;
    }

    /**
     * Whether the type needs an option selection before it can be added.
     *
     * True for configurable/bundle/grouped and for simple products carrying
     * required custom options; false for plain simple/virtual/downloadable that
     * add in one click. Mirrors the listing card's discriminator so the two
     * surfaces agree on what "quick-add" means.
     *
     * @return bool
     */
    public function needsOptions(): bool
    {
        try {
            $product = $this->getProduct();

            return $product !== null && $product->getTypeInstance()->canConfigure($product);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Whether the product is a configurable (drives the swatch island).
     *
     * @return bool
     */
    public function isConfigurable(): bool
    {
        return $this->getTypeId() === 'configurable';
    }

    /**
     * Current sale price (special price applied) as a raw number.
     *
     * @return float
     */
    public function getFinalPrice(): float
    {
        try {
            $product = $this->getProduct();
            if ($product === null) {
                return 0.0;
            }

            return (float)$product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
        } catch (Throwable) {
            return 0.0;
        }
    }

    /**
     * List price before any discount, for the strikethrough.
     *
     * @return float
     */
    public function getRegularPrice(): float
    {
        try {
            $product = $this->getProduct();
            if ($product === null) {
                return 0.0;
            }

            return (float)$product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue();
        } catch (Throwable) {
            return 0.0;
        }
    }

    /**
     * Whether the product is currently discounted (regular > final).
     *
     * @return bool
     */
    public function isOnSale(): bool
    {
        return $this->getRegularPrice() > $this->getFinalPrice() && $this->getFinalPrice() > 0.0;
    }

    /**
     * WYSIWYG-filtered product description, safe to print raw.
     *
     * Runs the stored value through the catalog output filter (directives,
     * widgets, allowed-html).
     *
     * @return string
     */
    public function getDescriptionHtml(): string
    {
        return $this->attributeHtml('description');
    }

    /**
     * WYSIWYG-filtered short description, safe to print raw.
     *
     * @return string
     */
    public function getShortDescriptionHtml(): string
    {
        return $this->attributeHtml('short_description');
    }

    /**
     * Absolute URL the add-to-cart form POSTs to.
     *
     * @return string
     */
    public function getAddToCartAction(): string
    {
        $product = $this->getProduct();
        $params = $product ? ['product' => $product->getId()] : [];

        return $this->url->getUrl('checkout/cart/add', $params);
    }

    /**
     * Base64 referrer (uenc) for the no-JS cart redirect back to this page.
     *
     * @return string
     */
    public function getUenc(): string
    {
        return $this->urlHelper->getEncodedUrl();
    }

    /**
     * Current product id.
     *
     * @return int
     */
    public function getProductId(): int
    {
        $product = $this->getProduct();

        return $product ? (int)$product->getId() : 0;
    }

    /**
     * Format a raw amount in the current store currency, for JS-facing strings
     * (e.g. the configurable island's initial price) that the `price` filter
     * cannot reach.
     *
     * @param float $amount
     * @return string
     */
    public function formatPrice(float $amount): string
    {
        return (string)$this->priceCurrency->format($amount, false);
    }

    /**
     * Run a product attribute through the catalog output filter.
     *
     * @param string $attribute
     * @return string
     */
    private function attributeHtml(string $attribute): string
    {
        try {
            $product = $this->getProduct();
            $value = $product?->getData($attribute);
            if ($product === null || !$value) {
                return '';
            }

            return (string)$this->outputHelper->productAttribute($product, $value, $attribute);
        } catch (Throwable) {
            return '';
        }
    }
}
