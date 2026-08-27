<?php
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use MageObsidian\Checkout\Model\PlaceRedirect;

/**
 * Catches the redirect a payment method set while the order was being placed.
 *
 * Fires on checkout_submit_all_after, which the platform dispatches from
 * QuoteManagement::submit for every placement - REST included.
 */
class RememberPlaceRedirect implements ObserverInterface
{
    /**
     * @param PlaceRedirect $placeRedirect
     */
    public function __construct(private readonly PlaceRedirect $placeRedirect)
    {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getData('quote');
        $payment = $quote?->getPayment();

        $this->placeRedirect->remember($payment ? (string)$payment->getOrderPlaceRedirectUrl() : '');
    }
}
