<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\Model\Seo;

use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Storefront\Model\Config\SeoConfig;
use MageObsidian\Storefront\Model\Seo\StoreMediaUrl;
use MageObsidian\Storefront\Model\Seo\WebManifestBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The manifest is derived from the store's own configuration, never hardcoded.
 * Needs Magento Store types, so it runs in a Magento root.
 */
class WebManifestBuilderTest extends TestCase
{
    private StoreManagerInterface&MockObject $storeManager;
    private StoreMediaUrl&MockObject $mediaUrl;
    private SeoConfig&MockObject $config;

    protected function setUp(): void
    {
        if (!interface_exists(StoreManagerInterface::class)) {
            $this->markTestSkipped('Magento Store is not available in this runtime.');
        }
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->mediaUrl = $this->createMock(StoreMediaUrl::class);
        $this->config = $this->createMock(SeoConfig::class);

        $store = $this->createMock(Store::class);
        $store->method('getFrontendName')->willReturn('MageObsidian Demo');
        $store->method('getBaseUrl')->willReturn('https://shop.test/');
        $this->storeManager->method('getStore')->willReturn($store);

        $this->config->method('getManifestDisplay')->willReturn('standalone');
        $this->config->method('getManifestThemeColor')->willReturn('#0b0b0d');
        $this->config->method('getManifestBackgroundColor')->willReturn('#ffffff');
    }

    private function subject(): WebManifestBuilder
    {
        return new WebManifestBuilder($this->storeManager, $this->mediaUrl, $this->config);
    }

    public function testDerivesTheManifestFromTheStore(): void
    {
        $this->mediaUrl->method('getLogo')->willReturn('https://shop.test/media/logo/logo.png');
        $this->mediaUrl->method('getFavicon')->willReturn('https://shop.test/media/favicon/icon.ico');

        $manifest = $this->subject()->build();

        $this->assertSame('MageObsidian Demo', $manifest['name']);
        $this->assertSame('MageObsidian', $manifest['short_name']);
        $this->assertSame('https://shop.test/', $manifest['start_url']);
        $this->assertSame('https://shop.test/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('#0b0b0d', $manifest['theme_color']);
        $this->assertSame('#ffffff', $manifest['background_color']);
        $this->assertSame(
            [
                ['src' => 'https://shop.test/media/logo/logo.png', 'sizes' => 'any', 'type' => 'image/png'],
                ['src' => 'https://shop.test/media/favicon/icon.ico', 'sizes' => 'any', 'type' => 'image/x-icon'],
            ],
            $manifest['icons']
        );
    }

    public function testFallsBackToStandaloneForAnUnknownDisplayMode(): void
    {
        $config = $this->createMock(SeoConfig::class);
        $config->method('getManifestDisplay')->willReturn('nonsense');
        $this->mediaUrl->method('getLogo')->willReturn('');
        $this->mediaUrl->method('getFavicon')->willReturn('');

        $manifest = (new WebManifestBuilder($this->storeManager, $this->mediaUrl, $config))->build();

        $this->assertSame('standalone', $manifest['display']);
    }

    public function testDropsTheIconsKeyWhenTheStoreHasNoImagery(): void
    {
        $this->mediaUrl->method('getLogo')->willReturn('');
        $this->mediaUrl->method('getFavicon')->willReturn('');

        $this->assertArrayNotHasKey('icons', $this->subject()->build());
    }

    public function testDoesNotListTheSameFileTwice(): void
    {
        $this->mediaUrl->method('getLogo')->willReturn('https://shop.test/media/logo/logo.png');
        $this->mediaUrl->method('getFavicon')->willReturn('https://shop.test/media/logo/logo.png');

        $this->assertCount(1, $this->subject()->build()['icons']);
    }
}
