<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\ViewModel;

use Magento\Cookie\Helper\Cookie as CookieHelper;
use Magento\GoogleAdwords\Helper\Data;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Storefront\ViewModel\LegacyGoogleAdsConversion;
use PHPUnit\Framework\TestCase;

class LegacyGoogleAdsConversionTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Data::class) || !class_exists(CookieHelper::class)) {
            $this->markTestSkipped('Magento GoogleAdwords and Cookie are not available in this runtime.');
        }
    }

    private function viewModel(
        bool $active = true,
        bool $sendsCurrency = true,
        bool $restricted = false,
        bool $declined = false
    ): LegacyGoogleAdsConversion {
        $helper = $this->createStub(Data::class);
        $helper->method('isGoogleAdwordsActive')->willReturn($active);
        $helper->method('getConversionId')->willReturn(123456);
        $helper->method('getConversionLanguage')->willReturn('en');
        $helper->method('getConversionFormat')->willReturn('2');
        $helper->method('getConversionColor')->willReturn('FFFFFF');
        $helper->method('getConversionLabel')->willReturn('thanks');
        $helper->method('getConversionValue')->willReturn(99.5);
        $helper->method('hasSendConversionValueCurrency')->willReturn($sendsCurrency);
        $helper->method('getConversionValueCurrency')->willReturn($sendsCurrency ? 'USD' : false);
        $helper->method('getConversionJsSrc')->willReturn('https://www.googleadservices.com/pagead/conversion.js');
        $helper->method('getConversionImgSrc')
            ->willReturn('https://www.googleadservices.com/pagead/conversion/123456/?label=thanks&guid=ON&script=0');

        $cookies = $this->createStub(CookieHelper::class);
        $cookies->method('isCookieRestrictionModeEnabled')->willReturn($restricted);
        $cookies->method('isUserNotAllowSaveCookie')->willReturn($declined);

        $website = $this->createStub(WebsiteInterface::class);
        $website->method('getId')->willReturn('2');
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getWebsite')->willReturn($website);

        return new LegacyGoogleAdsConversion($helper, $cookies, $stores);
    }

    public function testItHandsTheConfiguredValuesToTheConversionScript(): void
    {
        $conversion = $this->viewModel()->conversion();

        $this->assertSame('https://www.googleadservices.com/pagead/conversion.js', $conversion['src']);
        $this->assertSame(
            [
                'google_conversion_id' => 123456,
                'google_conversion_language' => 'en',
                'google_conversion_format' => '2',
                'google_conversion_color' => 'FFFFFF',
                'google_conversion_label' => 'thanks',
                'google_conversion_value' => 99.5,
                'google_conversion_currency' => 'USD',
            ],
            $conversion['variables']
        );
    }

    public function testItLeavesTheCurrencyOutWhenTheStoreDoesNotSendIt(): void
    {
        $this->assertArrayNotHasKey(
            'google_conversion_currency',
            $this->viewModel(sendsCurrency: false)->conversion()['variables']
        );
    }

    public function testItCarriesTheConsentRuleOfTheStore(): void
    {
        $conversion = $this->viewModel(restricted: true)->conversion();

        $this->assertTrue($conversion['isCookieRestrictionModeEnabled']);
        $this->assertSame(2, $conversion['currentWebsite']);
        $this->assertSame(CookieHelper::IS_USER_ALLOWED_SAVE_COOKIE, $conversion['cookieName']);
    }

    public function testAnInactiveTagEmitsNothing(): void
    {
        $viewModel = $this->viewModel(active: false);

        $this->assertSame([], $viewModel->conversion());
        $this->assertSame('', $viewModel->pixelUrl());
    }

    public function testThePixelIsOfferedToAShopperWhoHasNotDeclinedCookies(): void
    {
        $this->assertStringContainsString('label=thanks', $this->viewModel()->pixelUrl());
    }

    public function testThePixelStaysOutForAShopperWhoHasNotAcceptedCookies(): void
    {
        $this->assertSame('', $this->viewModel(restricted: true, declined: true)->pixelUrl());
    }
}
