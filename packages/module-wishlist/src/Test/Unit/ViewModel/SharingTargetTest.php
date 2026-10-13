<?php
/**
 * This file is part of the MageObsidian - Wishlist project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Wishlist\Test\Unit\ViewModel;

use Magento\Wishlist\Controller\WishlistProviderInterface;
use Magento\Wishlist\Model\Wishlist;
use MageObsidian\Wishlist\ViewModel\SharingTarget;
use PHPUnit\Framework\TestCase;

class SharingTargetTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(WishlistProviderInterface::class)) {
            $this->markTestSkipped('Magento Wishlist is not available in this runtime.');
        }
    }

    public function testNamesTheWishListTheProviderResolved(): void
    {
        $wishlist = $this->createMock(Wishlist::class);
        $wishlist->method('getId')->willReturn('7');
        $provider = $this->createMock(WishlistProviderInterface::class);
        $provider->method('getWishlist')->willReturn($wishlist);

        $this->assertSame(7, (new SharingTarget($provider))->getWishlistId());
    }

    public function testNamesNoWishListWhenTheProviderRefusesTheRequest(): void
    {
        $provider = $this->createMock(WishlistProviderInterface::class);
        $provider->method('getWishlist')->willReturn(false);

        $this->assertNull((new SharingTarget($provider))->getWishlistId());
    }

    public function testNamesNoWishListThatIsNotSavedYet(): void
    {
        $wishlist = $this->createMock(Wishlist::class);
        $wishlist->method('getId')->willReturn(null);
        $provider = $this->createMock(WishlistProviderInterface::class);
        $provider->method('getWishlist')->willReturn($wishlist);

        $this->assertNull((new SharingTarget($provider))->getWishlistId());
    }
}
