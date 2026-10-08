<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\ViewModel;

use Magento\Framework\Session\Config\ConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Storefront\ViewModel\SiteConfig;
use PHPUnit\Framework\TestCase;

class SiteConfigTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(ConfigInterface::class) || !interface_exists(StoreManagerInterface::class)) {
            $this->markTestSkipped('The Magento framework is not available in this runtime.');
        }
    }

    private function viewModel(
        string $domain = '',
        string $path = '/',
        int $lifetime = 3600,
        bool $secure = false,
        string $sameSite = 'Lax',
        int $websiteId = 1,
        int $storeId = 1,
        string $currency = 'USD'
    ): SiteConfig {
        $session = $this->createStub(ConfigInterface::class);
        $session->method('getCookieDomain')->willReturn($domain);
        $session->method('getCookiePath')->willReturn($path);
        $session->method('getCookieLifetime')->willReturn($lifetime);
        $session->method('getCookieSecure')->willReturn($secure);
        $session->method('getCookieSameSite')->willReturn($sameSite);

        $website = $this->createStub(WebsiteInterface::class);
        $website->method('getId')->willReturn((string)$websiteId);
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getWebsite')->willReturn($website);
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn((string)$storeId);
        $store->method('getCurrentCurrencyCode')->willReturn($currency);
        $stores->method('getStore')->willReturn($store);

        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(static fn(string $route): string => 'https://shop.test/' . $route);

        return new SiteConfig($session, $stores, $url);
    }

    public function testItCarriesAVersionAndTheWebsiteId(): void
    {
        $config = $this->viewModel(websiteId: 3)->toArray();

        $this->assertSame(1, $config['version']);
        $this->assertSame(3, $config['websiteId']);
    }

    public function testItCarriesTheStoreViewAndTheCurrencyInForce(): void
    {
        $config = $this->viewModel(storeId: 4, currency: 'EUR')->toArray();

        $this->assertSame(4, $config['storeId']);
        $this->assertSame('EUR', $config['currency']);
    }

    public function testItNamesThePageThatExplainsWhatToDoWithoutCookies(): void
    {
        $this->assertSame('https://shop.test/cookie/index/noCookies', $this->viewModel()->toArray()['noCookiesUrl']);
    }

    public function testItPublishesEverythingTheCookieWritesNeed(): void
    {
        $config = $this->viewModel('shop.test', '/shop', 7200, true, 'Strict')->toArray();

        $this->assertSame(
            ['domain' => '.shop.test', 'path' => '/shop', 'lifetime' => 7200, 'secure' => true, 'sameSite' => 'strict'],
            $config['cookie']
        );
    }

    public function testAnEmptyDomainStaysEmptySoTheCookieStaysHostOnly(): void
    {
        $this->assertSame('', $this->viewModel('')->toArray()['cookie']['domain']);
    }

    public function testADomainThatAlreadyStartsWithADotIsLeftAlone(): void
    {
        $this->assertSame('.shop.test', $this->viewModel('.shop.test')->toArray()['cookie']['domain']);
    }

    public function testAnIpAddressIsNeverGivenALeadingDot(): void
    {
        $this->assertSame('10.0.0.5', $this->viewModel('10.0.0.5')->toArray()['cookie']['domain']);
    }

    public function testTheJsonCannotCloseTheScriptTagItSitsIn(): void
    {
        $json = $this->viewModel('shop.test</script>')->toJson();

        $this->assertStringNotContainsString('</script>', $json);
        $this->assertSame(1, json_decode($json, true)['version']);
    }
}
