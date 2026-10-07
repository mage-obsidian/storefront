<?php
/**
 * This file is part of the MageObsidian - Customer project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Customer\Test\Unit\ViewModel;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use MageObsidian\Customer\ViewModel\CheckoutContext;
use PHPUnit\Framework\TestCase;

class CheckoutContextTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(RequestInterface::class) || !interface_exists(UrlInterface::class)) {
            $this->markTestSkipped('The Magento framework is not available in this runtime.');
        }
    }

    private function viewModel(array $params): CheckoutContext
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            static fn(string $name, $default = null) => $params[$name] ?? $default
        );
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn(string $route, ?array $query = null): string => 'https://shop.test/' . $route
                . (empty($query) ? '' : '?' . http_build_query($query))
        );

        return new CheckoutContext($request, $url);
    }

    public function testItIsActiveWhenTheShopperCameFromTheCheckout(): void
    {
        $this->assertTrue($this->viewModel(['context' => 'checkout'])->isActive());
    }

    public function testItIsNotActiveForAnyOtherContext(): void
    {
        $this->assertFalse($this->viewModel(['context' => 'default'])->isActive());
        $this->assertFalse($this->viewModel([])->isActive());
    }

    public function testALinkCarriesTheContextAndTheWayBackWhenActive(): void
    {
        $url = $this->viewModel(['context' => 'checkout', 'referer' => 'abc'])->urlTo('customer/account/create');

        $this->assertSame('https://shop.test/customer/account/create?context=checkout&referer=abc', $url);
    }

    public function testALinkIsPlainWhenNotActive(): void
    {
        $this->assertSame(
            'https://shop.test/customer/account/create',
            $this->viewModel(['referer' => 'abc'])->urlTo('customer/account/create')
        );
    }

    public function testALinkLeavesOutAWayBackThatWasNeverGiven(): void
    {
        $this->assertSame(
            'https://shop.test/customer/account/login?context=checkout',
            $this->viewModel(['context' => 'checkout'])->urlTo('customer/account/login')
        );
    }

    public function testItHandsBackTheWayTheShopperCameSo(): void
    {
        $this->assertSame('abc', $this->viewModel(['context' => 'checkout', 'referer' => 'abc'])->getReferer());
    }

    public function testItHasNoWayBackWhenNoneWasGiven(): void
    {
        $this->assertSame('', $this->viewModel(['context' => 'checkout'])->getReferer());
    }
}
