<?php
/**
 * This file is part of the MageObsidian - Wishlist project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Wishlist\Test\Unit\ViewModel;

use Magento\Framework\UrlInterface;
use Magento\Wishlist\Helper\Data as WishlistHelper;
use MageObsidian\Wishlist\ViewModel\CartMove;
use PHPUnit\Framework\TestCase;

/**
 * "Move to wish list" cart action. We assert the visibility gate delegates to the
 * native isAllowInCart rule and that the move URL points at the fromcart
 * controller.
 */
class CartMoveTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(WishlistHelper::class)) {
            $this->markTestSkipped('Magento Wishlist is not available in this runtime.');
        }
    }

    public function testIsAllowedDelegatesToTheHelper(): void
    {
        $helper = $this->createMock(WishlistHelper::class);
        $helper->method('isAllowInCart')->willReturn(true);

        $this->assertTrue((new CartMove($helper, $this->createMock(UrlInterface::class)))->isAllowed());
    }

    public function testIsNotAllowedForGuestsOrDisabledWishList(): void
    {
        $helper = $this->createMock(WishlistHelper::class);
        $helper->method('isAllowInCart')->willReturn(false);

        $this->assertFalse((new CartMove($helper, $this->createMock(UrlInterface::class)))->isAllowed());
    }

    public function testMoveUrlPointsAtTheFromcartController(): void
    {
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->with('wishlist/index/fromcart')
            ->willReturn('https://shop.test/wishlist/index/fromcart');

        $view = new CartMove($this->createMock(WishlistHelper::class), $url);

        $this->assertSame('https://shop.test/wishlist/index/fromcart', $view->getMoveUrl());
    }
}
