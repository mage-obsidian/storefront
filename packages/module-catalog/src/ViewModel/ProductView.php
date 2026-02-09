<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Catalog\ViewModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Output as OutputHelper;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\App\Request\Http as Request;
use Magento\Framework\Pricing\Render as PriceRender;
use Magento\Framework\Registry;
use Magento\Framework\Url\Helper\Data as UrlHelper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\LayoutInterface;
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
     * @param LayoutInterface $layout
     * @param Request $request
     */
    public function __construct(
        private readonly Registry $registry,
        private readonly OutputHelper $outputHelper,
        private readonly UrlInterface $url,
        private readonly UrlHelper $urlHelper,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly LayoutInterface $layout,
        private readonly Request $request
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
     * Whether the product is a downloadable (drives the link selector form).
     *
     * @return bool
     */
    public function isDownloadable(): bool
    {
        return $this->getTypeId() === 'downloadable';
    }

    /**
     * Whether the product is a grouped (drives the associated-products table).
     *
     * @return bool
     */
    public function isGrouped(): bool
    {
        return $this->getTypeId() === 'grouped';
    }

    /**
     * Whether the product is a bundle (drives the option selector form).
     *
     * @return bool
     */
    public function isBundle(): bool
    {
        return $this->getTypeId() === 'bundle';
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
     * Native final-price markup for the buy box (special/tier/range), re-skinned
     * to OBSIDIAN via CSS. The render block is restored by this module's
     * default.xml; empty when it or the product is unavailable.
     *
     * @return string
     */
    public function getPriceHtml(): string
    {
        try {
            $product = $this->getProduct();
            $priceRender = $this->layout->getBlock('product.price.render.default');
            if ($product === null || !$priceRender instanceof PriceRender) {
                return '';
            }

            return (string)$priceRender->render(
                'final_price',
                $product,
                ['zone' => 'item_view', 'use_link_for_as_low_as' => true]
            );
        } catch (Throwable) {
            return '';
        }
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
        if ($this->isConfigureMode()) {
            return $this->url->getUrl('checkout/cart/updateItemOptions', [
                'id' => $this->getConfiguredItemId(),
                'product_id' => $product ? $product->getId() : 0,
            ]);
        }
        $params = $product ? ['product' => $product->getId()] : [];

        return $this->url->getUrl('checkout/cart/add', $params);
    }

    /**
     * Whether the PDP is rendered to reconfigure an existing cart item.
     *
     * @return bool
     */
    public function isConfigureMode(): bool
    {
        return $this->request->getFullActionName() === 'checkout_cart_configure';
    }

    /**
     * Quote item id being reconfigured (0 outside configure mode).
     *
     * @return int
     */
    public function getConfiguredItemId(): int
    {
        return (int)$this->request->getParam('id');
    }

    /**
     * Submit-button label: "Update Cart" when reconfiguring, else "Add to cart".
     *
     * @return string
     */
    public function getSubmitLabel(): string
    {
        return (string)($this->isConfigureMode() ? __('Update Cart') : __('Add to cart'));
    }

    /**
     * Preconfigured super-attribute selection (attributeId => optionId) for a
     * configurable being reconfigured, so the island can preselect it. Empty on a
     * normal product view.
     *
     * @return array<int|string, int|string>
     */
    public function getPreconfiguredSuperAttribute(): array
    {
        try {
            $product = $this->getProduct();
            if ($product === null) {
                return [];
            }
            $superAttribute = $product->getPreconfiguredValues()->getSuperAttribute();

            return is_array($superAttribute) ? $superAttribute : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Preconfigured quantity for a reconfigured item (1 by default).
     *
     * @return float
     */
    public function getPreconfiguredQty(): float
    {
        try {
            $product = $this->getProduct();
            $qty = $product?->getPreconfiguredValues()->getQty();

            return $qty > 0 ? (float)$qty : 1.0;
        } catch (Throwable) {
            return 1.0;
        }
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
     * Store currency output pattern with a `%s` placeholder (e.g. "$%s"), so the
     * options enhancer can format a live total (base price + option deltas)
     * client-side, mirroring the configurable island's currencyFormat.
     *
     * @return string
     */
    public function getCurrencyFormat(): string
    {
        return (string)$this->priceCurrency->getCurrency()->getOutputFormat();
    }

    /**
     * Selectable years for a date custom option (current year through +20),
     * matching Magento's default date-range behaviour. Rendered as dropdowns so
     * a date option works without JS (no calendar widget).
     *
     * @return array<int, int>
     */
    public function getOptionYears(): array
    {
        $start = (int)date('Y');

        return range($start, $start + 20);
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
