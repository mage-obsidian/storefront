<?php
/**
 * This file is part of the MageObsidian - InstantPurchase project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\InstantPurchase\ViewModel;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\InstantPurchase\Model\Config;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

/**
 * PDP instant-purchase button config, consumed from the product-view Twig as
 * `block.getInstantPurchase()`. It self-gates on the native module flag; the
 * deeper availability (a stored token + default addresses + shipping method) is
 * decided client-side by the native `instant-purchase` customer-data section,
 * which stays empty without a configured tokenizing gateway.
 */
class InstantPurchase implements ArgumentInterface
{
    private const PLACE_ORDER_ROUTE = 'instantpurchase/button/placeOrder';

    /**
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $url
     */
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $url
    ) {
    }

    /**
     * Whether the instant-purchase module is enabled for the current store.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        try {
            return $this->config->isModuleEnabled($this->storeId());
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The configured button label.
     *
     * @return string
     */
    public function getButtonText(): string
    {
        try {
            return (string)$this->config->getButtonText($this->storeId());
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Secure URL of the native place-order controller.
     *
     * @return string
     */
    public function getPurchaseUrl(): string
    {
        return $this->url->getUrl(self::PLACE_ORDER_ROUTE, ['_secure' => true]);
    }

    /**
     * Current store id.
     *
     * @return int
     */
    private function storeId(): int
    {
        return (int)$this->storeManager->getStore()->getId();
    }
}
