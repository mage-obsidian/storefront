<?php
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\Checkout\Model;

use Magento\Checkout\Model\Session as CheckoutSession;

/**
 * Where a payment method wants the shopper next, held between the order being
 * placed and the island acting on it.
 *
 * A gateway computes that URL while it places the order - it needs the token its
 * own API just handed back - so it is not knowable when the checkout renders, and
 * REST answers with an order id alone. The platform reads it off the quote payment
 * (Magento\Checkout\Model\Type\Onepage::saveOrder does the same); this keeps it for
 * the one navigation that follows.
 */
class PlaceRedirect
{
    public const string SESSION_KEY = 'mage_obsidian_place_redirect';

    /**
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(private readonly CheckoutSession $checkoutSession)
    {
    }

    /**
     * Remember where the method wants the shopper. An empty URL clears it.
     *
     * @param string $url
     * @return void
     */
    public function remember(string $url): void
    {
        $this->checkoutSession->setMageObsidianPlaceRedirect(trim($url) === '' ? null : trim($url));
    }

    /**
     * The remembered URL, cleared as it is read: it belongs to one order only.
     *
     * @return string
     */
    public function take(): string
    {
        return (string)$this->checkoutSession->getData(self::SESSION_KEY, true);
    }
}
