<?php
/**
 * This file is part of the MageObsidian - InventoryInStorePickup project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\InventoryInStorePickup\Test\Unit\ViewModel;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\InventoryInStorePickupApi\Model\SearchRequest\Area\SearchTerm\DelimiterConfig;
use Magento\Store\Model\Store;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\InventoryInStorePickup\ViewModel\InStorePickupRenderer;
use PHPUnit\Framework\TestCase;

class InStorePickupRendererTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(DelimiterConfig::class) || !class_exists(DirectoryHelper::class)) {
            $this->markTestSkipped('Magento and the pickup modules are not available in this runtime.');
        }
    }

    private function renderer(string $radius = '200', string $delimiter = ':'): InStorePickupRenderer
    {
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop.test/');
        $store->method('getCode')->willReturn('default');
        $website = $this->createStub(WebsiteInterface::class);
        $website->method('getCode')->willReturn('base');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $storeManager->method('getWebsite')->willReturn($website);
        $store->method('getWebsiteId')->willReturn(1);

        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnMap([['carriers/instore/search_radius', 'store', null, $radius]]);
        $delimiterConfig = $this->createStub(DelimiterConfig::class);
        $delimiterConfig->method('getDelimiter')->willReturn($delimiter);
        $directory = $this->createStub(DirectoryHelper::class);
        $directory->method('getDefaultCountry')->willReturn('US');

        return new InStorePickupRenderer($storeManager, $scopeConfig, $delimiterConfig, $directory);
    }

    public function testItOwnsTheInStoreCarrier(): void
    {
        $this->assertSame(['instore'], $this->renderer()->getCarrierCodes());
    }

    public function testItNamesAQualifiedComponentOfItsOwnModule(): void
    {
        $this->assertSame(
            'MageObsidian_InventoryInStorePickup::shipping/InStorePickup',
            $this->renderer()->getComponent()
        );
    }

    public function testItHandsTheComponentWhatTheCoreEndpointNeeds(): void
    {
        $config = $this->renderer('150', '|')->getConfig();

        $this->assertSame('https://shop.test/rest/default/V1/', $config['restBaseUrl']);
        $this->assertSame('base', $config['scopeCode']);
        $this->assertSame('|', $config['delimiter']);
        $this->assertSame(150, $config['radius']);
        $this->assertSame('US', $config['defaultCountry']);
        $this->assertGreaterThan(0, $config['pageSize']);
    }

    public function testItFallsBackToTheCoreDefaultRadiusWhenTheSettingIsEmpty(): void
    {
        $this->assertSame(200, $this->renderer('')->getConfig()['radius']);
    }

    public function testItShipsTheLabelsTheComponentDraws(): void
    {
        $labels = $this->renderer()->getConfig()['labels'];

        foreach (['delivery', 'ship', 'pickup', 'search', 'find', 'none', 'loading', 'failed', 'choose', 'chosen'] as $key) {
            $this->assertNotSame('', $labels[$key] ?? '', $key);
        }
    }
}
