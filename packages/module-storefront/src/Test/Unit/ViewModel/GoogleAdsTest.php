<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\ViewModel;

use Magento\Checkout\Model\Session;
use Magento\Cookie\Helper\Cookie as CookieHelper;
use Magento\GoogleGtag\Model\Config\GtagConfig;
use Magento\Sales\Model\Order;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Storefront\ViewModel\GoogleAds;
use PHPUnit\Framework\TestCase;

class GoogleAdsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(GtagConfig::class) || !class_exists(Session::class) || !class_exists(CookieHelper::class)) {
            $this->markTestSkipped('Magento GoogleGtag, Checkout and Cookie are not available in this runtime.');
        }
    }

    private function viewModel(
        bool $configurable = true,
        bool $active = true,
        bool $restricted = false,
        ?Order $order = null
    ): GoogleAds {
        $config = $this->createStub(GtagConfig::class);
        $config->method('isGoogleAdwordsConfigurable')->willReturn($configurable);
        $config->method('isGoogleAdwordsActive')->willReturn($active);
        $config->method('getConversionId')->willReturn('AW-123456');
        $config->method('getConversionLabel')->willReturn('thanks');
        $config->method('getConversionGtagGlobalSiteTagSrc')
            ->willReturn('https://www.googletagmanager.com/gtag/js?id=AW-123456');

        $cookies = $this->createStub(CookieHelper::class);
        $cookies->method('isCookieRestrictionModeEnabled')->willReturn($restricted);

        $website = $this->createStub(WebsiteInterface::class);
        $website->method('getId')->willReturn('2');
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getWebsite')->willReturn($website);

        $session = $this->createStub(Session::class);
        $session->method('getLastRealOrder')->willReturn($order ?? $this->createStub(Order::class));

        return new GoogleAds($config, $cookies, $stores, $session);
    }

    private function placedOrder(): Order
    {
        $order = $this->createStub(Order::class);
        $order->method('getId')->willReturn(7);
        $order->method('getIncrementId')->willReturn('000000123');
        $order->method('getGrandTotal')->willReturn(99.5);
        $order->method('getOrderCurrencyCode')->willReturn('USD');

        return $order;
    }

    public function testTheTagCarriesTheConfiguredIdAndTheLibraryUrl(): void
    {
        $tag = $this->viewModel()->tag();

        $this->assertSame('AW-123456', $tag['conversionId']);
        $this->assertSame('https://www.googletagmanager.com/gtag/js?id=AW-123456', $tag['src']);
        $this->assertArrayNotHasKey('conversion', $tag);
    }

    public function testTheTagCarriesTheConsentRuleOfTheStore(): void
    {
        $tag = $this->viewModel(restricted: true)->tag();

        $this->assertTrue($tag['isCookieRestrictionModeEnabled']);
        $this->assertSame(2, $tag['currentWebsite']);
        $this->assertSame(CookieHelper::IS_USER_ALLOWED_SAVE_COOKIE, $tag['cookieName']);
    }

    public function testAStoreWithoutAnIdEmitsNothing(): void
    {
        $viewModel = $this->viewModel(configurable: false, active: false, order: $this->placedOrder());

        $this->assertSame([], $viewModel->tag());
        $this->assertSame([], $viewModel->conversion());
    }

    public function testTheConversionCarriesTheValueAndCurrencyOfTheOrder(): void
    {
        $conversion = $this->viewModel(order: $this->placedOrder())->conversion();

        $this->assertSame(
            ['sendTo' => 'AW-123456/thanks', 'value' => 99.5, 'currency' => 'USD', 'transactionId' => '000000123'],
            $conversion['conversion']
        );
        $this->assertSame('AW-123456', $conversion['conversionId']);
    }

    public function testWithoutALabelThereIsATagButNoConversion(): void
    {
        $viewModel = $this->viewModel(active: false, order: $this->placedOrder());

        $this->assertSame('AW-123456', $viewModel->conversion()['conversionId']);
        $this->assertArrayNotHasKey('conversion', $viewModel->conversion());
    }

    public function testWithoutAPlacedOrderThereIsNoConversion(): void
    {
        $this->assertArrayNotHasKey('conversion', $this->viewModel()->conversion());
    }
}
