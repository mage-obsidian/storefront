<?php
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\ViewModel;

use Magento\Framework\Url\EncoderInterface;
use Magento\Framework\UrlInterface;
use MageObsidian\Checkout\ViewModel\SignIn;
use PHPUnit\Framework\TestCase;

class SignInTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(UrlInterface::class) || !interface_exists(EncoderInterface::class)) {
            $this->markTestSkipped('The Magento framework is not available in this runtime.');
        }
    }

    private function viewModel(): SignIn
    {
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn(string $route, ?array $params = null): string => 'https://shop.test/' . $route
                . (empty($params) ? '' : '?' . http_build_query($params))
        );
        $encoder = $this->createStub(EncoderInterface::class);
        $encoder->method('encode')->willReturnCallback(static fn(string $value): string => 'enc(' . $value . ')');

        return new SignIn($url, $encoder);
    }

    public function testItSendsTheShopperBackToTheCheckoutOnceSignedIn(): void
    {
        $this->assertStringContainsString('referer=enc%28https%3A%2F%2Fshop.test%2Fcheckout%29', $this->viewModel()->getUrl());
    }

    public function testItSaysTheShopperIsComingFromTheCheckout(): void
    {
        $this->assertStringContainsString('context=checkout', $this->viewModel()->getUrl());
    }

    public function testItPointsAtTheSignInPage(): void
    {
        $this->assertStringStartsWith('https://shop.test/customer/account/login', $this->viewModel()->getUrl());
    }
}
