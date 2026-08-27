<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Catalog\ViewModel;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

/**
 * What a product page tells a link preview about itself.
 *
 * MageObsidian suppresses the core catalog layout, and with it the
 * catalog_product_opengraph handle, so a product shared into a chat client or a
 * social post previewed as a bare URL. This re-expresses the same set core emits
 * — type, title, image, description, url and the price with its currency — as
 * one array the head template walks, so a tag with nothing behind it is simply
 * absent rather than empty.
 */
class ProductOpenGraph implements ArgumentInterface
{
    /**
     * @param Registry $registry
     * @param ImageHelper $imageHelper
     * @param StoreManagerInterface $storeManager
     * @param CurrencyFactory $currencyFactory
     */
    public function __construct(
        private readonly Registry $registry,
        private readonly ImageHelper $imageHelper,
        private readonly StoreManagerInterface $storeManager,
        private readonly CurrencyFactory $currencyFactory
    ) {
    }

    /**
     * Current product, or null off a product page.
     *
     * @return Product|null
     */
    public function getProduct(): ?Product
    {
        $product = $this->registry->registry('current_product');

        return $product instanceof Product ? $product : null;
    }

    /**
     * The properties the head should emit, in order, keyed by property name.
     *
     * @return array<string, string>
     */
    public function getProperties(): array
    {
        $product = $this->getProduct();
        if ($product === null) {
            return [];
        }

        $properties = [
            'og:type' => 'product',
            'og:title' => $this->plain((string)$product->getName()),
            'og:url' => (string)$product->getProductUrl(),
        ];

        $image = $this->image($product);
        if ($image !== '') {
            $properties['og:image'] = $image;
        }

        $description = $this->plain((string)$product->getData('short_description'));
        if ($description !== '') {
            $properties['og:description'] = $description;
        }

        $price = $this->price($product);
        if ($price !== null) {
            $properties['product:price:amount'] = number_format($price, 2, '.', '');
            $properties['product:price:currency'] = $this->currency();
        }

        return $properties;
    }

    /**
     * @param Product $product
     *
     * @return string
     */
    private function image(Product $product): string
    {
        try {
            return (string)$this->imageHelper->init($product, 'product_base_image')->getUrl();
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @param Product $product
     *
     * @return float|null
     */
    private function price(Product $product): ?float
    {
        if ($product->getData('can_show_price') === false) {
            return null;
        }

        try {
            $amount = $product->getPriceInfo()->getPrice(FinalPrice::PRICE_CODE)->getAmount()->getValue();

            return $amount === null ? null : (float)$amount;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return string
     */
    private function currency(): string
    {
        try {
            return (string)$this->storeManager->getStore()->getCurrentCurrencyCode();
        } catch (Throwable) {
            return (string)$this->currencyFactory->create()->getCode();
        }
    }

    /**
     * @param string $value
     *
     * @return string
     */
    private function plain(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? '');
    }
}
