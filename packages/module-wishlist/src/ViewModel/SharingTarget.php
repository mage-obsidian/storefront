<?php
/**
 * This file is part of the MageObsidian - Wishlist project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace MageObsidian\Wishlist\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Wishlist\Controller\WishlistProviderInterface;

class SharingTarget implements ArgumentInterface
{
    public function __construct(
        private readonly WishlistProviderInterface $wishlistProvider
    ) {
    }

    public function getWishlistId(): ?int
    {
        $wishlist = $this->wishlistProvider->getWishlist();
        if (!$wishlist || !$wishlist->getId()) {
            return null;
        }

        return (int)$wishlist->getId();
    }
}
