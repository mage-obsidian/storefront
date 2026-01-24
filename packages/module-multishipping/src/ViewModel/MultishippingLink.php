<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Multishipping project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Multishipping\ViewModel;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Multishipping\Helper\Data as MultishippingHelper;
use Throwable;

/**
 * "Check Out with Multiple Addresses" cart entry point, consumed from the bag
 * Twig as `block.getMultishippingLink()`. Self-gates on the native availability
 * helper (feature flag + the quote being multi-shipping eligible), so the link
 * renders only when multishipping checkout is actually possible.
 */
class MultishippingLink implements ArgumentInterface
{
    private const CHECKOUT_ROUTE = 'multishipping/checkout/login';

    /**
     * @param MultishippingHelper $helper
     * @param UrlInterface $url
     */
    public function __construct(
        private readonly MultishippingHelper $helper,
        private readonly UrlInterface $url
    ) {
    }

    /**
     * Whether multishipping checkout is available for the current quote.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        try {
            return (bool)$this->helper->isMultishippingCheckoutAvailable();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Entry URL into the multi-address checkout (routes through login when guest).
     *
     * @return string
     */
    public function getCheckoutUrl(): string
    {
        return $this->url->getUrl(self::CHECKOUT_ROUTE);
    }
}
