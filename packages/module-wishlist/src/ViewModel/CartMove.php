<?php
/**
 * This file is part of the MageObsidian - Wishlist project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace MageObsidian\Wishlist\ViewModel;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Wishlist\Helper\Data as WishlistHelper;

/**
 * "Move to wish list" action for cart lines.
 *
 * Injected into the cart block from this module's layout so Magento_Checkout
 * stays unaware of wish lists. `isAllowed()` gates the control on the native
 * rule (wish list enabled AND a logged-in customer — guests have no wish list),
 * mirroring core's MoveToWishlist renderer; the cart Twig posts the item id to
 * the URL from `getMoveUrl()` (wishlist/index/fromcart), which removes the line
 * and adds the product to the wish list.
 */
class CartMove implements ArgumentInterface
{
    /**
     * @param WishlistHelper $wishlistHelper
     * @param UrlInterface $url
     */
    public function __construct(
        private readonly WishlistHelper $wishlistHelper,
        private readonly UrlInterface $url
    ) {
    }

    /**
     * Whether the move-to-wish-list action should be offered.
     *
     * @return bool
     */
    public function isAllowed(): bool
    {
        return (bool)$this->wishlistHelper->isAllowInCart();
    }

    /**
     * Controller URL a cart line posts its item id to.
     *
     * @return string
     */
    public function getMoveUrl(): string
    {
        return $this->url->getUrl('wishlist/index/fromcart');
    }
}
