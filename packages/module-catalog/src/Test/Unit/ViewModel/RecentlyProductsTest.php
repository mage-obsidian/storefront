<?php
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Catalog\Test\Unit\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Catalog\ViewModel\RecentlyProducts;
use PHPUnit\Framework\TestCase;

class RecentlyProductsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Product::class)) {
            $this->markTestSkipped('Magento Catalog is not available in this runtime.');
        }
    }

    private function viewModel(array $config = [], ?Product $product = null): RecentlyProducts
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn(string $path): mixed => $config[$path] ?? null
        );
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn(string $path): bool => (bool)($config[$path] ?? false)
        );

        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(3);
        $store->method('getStoreGroupId')->willReturn(2);
        $store->method('getWebsiteId')->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $urlBuilder = $this->createStub(UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturnCallback(static fn(string $route): string => 'https://shop.test/' . $route . '/');

        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturn($product);

        return new RecentlyProducts($scopeConfig, $storeManager, $urlBuilder, $registry);
    }

    public function testScopeFollowsTheStoreWhenTheMerchantSetsIt(): void
    {
        $this->assertSame('store-3', $this->viewModel([RecentlyProducts::SCOPE_PATH => 'store'])->config()['scope']);
    }

    public function testScopeFollowsTheStoreGroupWhenTheMerchantSetsIt(): void
    {
        $this->assertSame('group-2', $this->viewModel([RecentlyProducts::SCOPE_PATH => 'group'])->config()['scope']);
    }

    public function testScopeDefaultsToTheWebsite(): void
    {
        $this->assertSame('website-1', $this->viewModel()->config()['scope']);
        $this->assertSame('website-1', $this->viewModel([RecentlyProducts::SCOPE_PATH => 'nonsense'])->config()['scope']);
    }

    public function testLifetimesAreReadFromTheConfiguration(): void
    {
        $config = $this->viewModel([
            RecentlyProducts::VIEWED_LIFETIME_PATH => '120',
            RecentlyProducts::COMPARED_LIFETIME_PATH => '30',
        ])->config();

        $this->assertSame(120, $config['viewedLifetime']);
        $this->assertSame(30, $config['comparedLifetime']);
    }

    public function testAMissingOrInvalidLifetimeFallsBackToThePlatformDefault(): void
    {
        $config = $this->viewModel([RecentlyProducts::VIEWED_LIFETIME_PATH => 'soon'])->config();

        $this->assertSame(1000, $config['viewedLifetime']);
        $this->assertSame(1000, $config['comparedLifetime']);
    }

    public function testSynchronisationFollowsTheSetting(): void
    {
        $this->assertTrue($this->viewModel([RecentlyProducts::SYNC_PATH => '1'])->config()['sync']);
        $this->assertFalse($this->viewModel()->config()['sync']);
    }

    public function testTheSynchronisationAndCardsAddressesPointAtTheirRoutes(): void
    {
        $viewModel = $this->viewModel();

        $this->assertSame('https://shop.test/catalog/product/frontend_action_synchronize/', $viewModel->config()['syncUrl']);
        $this->assertSame('https://shop.test/mageobsidian-catalog/recent/cards/', $viewModel->cardsUrl());
    }

    public function testTheCurrentProductIdIsKnownOnAProductPage(): void
    {
        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn(42);

        $this->assertSame('42', $this->viewModel([], $product)->currentProductId());
        $this->assertNull($this->viewModel()->currentProductId());
    }
}
