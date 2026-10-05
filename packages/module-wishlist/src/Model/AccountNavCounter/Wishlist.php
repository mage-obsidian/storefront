<?php
/**
 * This file is part of the MageObsidian - Wishlist project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace MageObsidian\Wishlist\Model\AccountNavCounter;

use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Wishlist\Model\WishlistFactory;
use MageObsidian\Customer\Api\AccountNavCounterInterface;

/**
 * Badge for the "My Wish List" rail entry.
 *
 * Deliberately not Wishlist\Helper\Data::getItemCount(): that counts the single
 * item collection the helper hands out, and on the wish list page the block has
 * already page-sized it through the pager — so the badge read "10" next to a list
 * of 11, and the wrong number was then cached in the session for every other page.
 * A wishlist of its own with a plain COUNT is both isolated and cheaper.
 */
class Wishlist implements AccountNavCounterInterface
{
    public function __construct(
        private readonly WishlistFactory $wishlistFactory,
        private readonly CurrentCustomer $currentCustomer
    ) {
    }

    public function getCount(): ?int
    {
        $customerId = (int)$this->currentCustomer->getCustomerId();
        if ($customerId === 0) {
            return null;
        }

        return (int)$this->wishlistFactory->create()
            ->loadByCustomerId($customerId)
            ->getItemCollection()
            ->getSize();
    }
}
