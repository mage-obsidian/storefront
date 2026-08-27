<?php
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\Checkout\Controller\Onepage;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use MageObsidian\Checkout\Model\PlaceRedirect;

/**
 * The one place the island sends a shopper after the order is placed.
 *
 * Only the server knows whether the payment method wants them somewhere else
 * first, so the island navigates here and this decides: the gateway when one
 * asked for it, the success page otherwise. Without it a method whose flow
 * continues off-site lands the shopper on the success page with the payment
 * untaken.
 */
class Handover implements HttpGetActionInterface
{
    /**
     * @param RedirectFactory $redirectFactory
     * @param PlaceRedirect $placeRedirect
     */
    public function __construct(
        private readonly RedirectFactory $redirectFactory,
        private readonly PlaceRedirect $placeRedirect
    ) {
    }

    /**
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $redirect = $this->redirectFactory->create();
        $url = $this->placeRedirect->take();

        return $url === ''
            ? $redirect->setPath('checkout/onepage/success')
            : $redirect->setUrl($url);
    }
}
