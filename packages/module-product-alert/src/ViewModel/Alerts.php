<?php
/**
 * This file is part of the MageObsidian - ProductAlert project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\ProductAlert\ViewModel;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\ProductAlert\Helper\Data as ProductAlertHelper;
use Throwable;

/**
 * Price-drop / back-in-stock notify links for the PDP.
 *
 * Injected into the product-view block so Magento_Catalog stays unaware of the
 * feature. Each link self-gates: price needs the alert enabled AND a shown price;
 * stock needs it enabled AND an out-of-stock product (matching the native blocks).
 */
class Alerts implements ArgumentInterface
{
    /**
     * @param ProductAlertHelper $helper
     * @param Registry $registry
     */
    public function __construct(
        private readonly ProductAlertHelper $helper,
        private readonly Registry $registry
    ) {
    }

    /**
     * Whether the price-drop notify link should show.
     *
     * @return bool
     */
    public function isPriceAllowed(): bool
    {
        $product = $this->product();

        return $product !== null
            && (bool)$this->helper->isPriceAlertAllowed()
            && $product->getCanShowPrice() !== false;
    }

    /**
     * Whether the back-in-stock notify link should show.
     *
     * @return bool
     */
    public function isStockAllowed(): bool
    {
        $product = $this->product();

        return $product !== null
            && (bool)$this->helper->isStockAlertAllowed()
            && !$product->isAvailable();
    }

    /**
     * Signup URL for the price-drop alert.
     *
     * @return string
     */
    public function getPriceUrl(): string
    {
        return $this->saveUrl('price');
    }

    /**
     * Signup URL for the back-in-stock alert.
     *
     * @return string
     */
    public function getStockUrl(): string
    {
        return $this->saveUrl('stock');
    }

    /**
     * Signup URL for an alert type, or empty off a product page.
     *
     * @param string $type
     * @return string
     */
    private function saveUrl(string $type): string
    {
        try {
            return (string)$this->helper->getSaveUrl($type);
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Current product, or null off a product page.
     *
     * @return object|null
     */
    private function product(): ?object
    {
        $product = $this->registry->registry('current_product');

        return ($product && $product->getId()) ? $product : null;
    }
}
