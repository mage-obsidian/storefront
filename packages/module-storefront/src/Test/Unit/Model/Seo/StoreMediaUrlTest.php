<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\Model\Seo;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Storefront\Model\Seo\StoreMediaUrl;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Media URLs the head needs, built off the store's media base URL. Needs
 * Magento Store types, so it runs in a Magento root.
 */
class StoreMediaUrlTest extends TestCase
{
    private StoreManagerInterface&MockObject $storeManager;
    private ScopeConfigInterface&MockObject $scopeConfig;

    protected function setUp(): void
    {
        if (!interface_exists(StoreManagerInterface::class)) {
            $this->markTestSkipped('Magento Store is not available in this runtime.');
        }
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
    }

    private function subject(string $mediaBase = 'https://shop.test/media/'): StoreMediaUrl
    {
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn($mediaBase);
        $this->storeManager->method('getStore')->willReturn($store);

        return new StoreMediaUrl($this->storeManager, $this->scopeConfig);
    }

    public function testBuildsTheLogoUrlFromTheDesignConfiguration(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('stores/1/logo.png');

        $this->assertSame('https://shop.test/media/logo/stores/1/logo.png', $this->subject()->getLogo());
    }

    public function testBuildsTheFaviconUrl(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('icon.ico');

        $this->assertSame('https://shop.test/media/favicon/icon.ico', $this->subject()->getFavicon());
    }

    public function testJoinsTheProductImagePathWithoutDoublingSlashes(): void
    {
        $this->assertSame(
            'https://shop.test/media/catalog/product/j/o/joust.jpg',
            $this->subject()->getProductImage('/j/o/joust.jpg')
        );
    }

    public function testTreatsNoSelectionAsNoImage(): void
    {
        $this->assertSame('', $this->subject()->getProductImage('no_selection'));
        $this->assertSame('', $this->subject()->getProductImage(''));
    }

    public function testReturnsEmptyWhenTheStoreHasNoMediaUrl(): void
    {
        $this->assertSame('', $this->subject('')->getProductImage('/j/o/joust.jpg'));
    }

    public function testReturnsEmptyForAnUnconfiguredFile(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('');

        $this->assertSame('', $this->subject()->getLogo());
    }
}
