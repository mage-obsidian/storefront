<?php
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Catalog\Test\Unit\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Api\Data\StockStatusInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\Store;
use MageObsidian\Catalog\ViewModel\ProductStock;
use PHPUnit\Framework\TestCase;

class ProductStockTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Product::class) || !interface_exists(StockRegistryInterface::class)) {
            $this->markTestSkipped('Magento Catalog Inventory is not available in this runtime.');
        }
    }

    private function viewModel(
        bool|float $increments = false,
        float $minSaleQty = 1.0,
        bool $onProductPage = true,
        array $settings = [],
        float $quantity = 100.0,
        float $stockMinQty = 0.0,
        bool $available = true
    ): ProductStock {
        $store = $this->createMock(Store::class);
        $store->method('getWebsiteId')->willReturn(1);

        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn(7);
        $product->method('getStore')->willReturn($store);
        $product->method('isAvailable')->willReturn($available);

        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->with('current_product')->willReturn($onProductPage ? $product : null);

        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->method('getQtyIncrements')->willReturn($increments);
        $stockItem->method('getMinSaleQty')->willReturn($minSaleQty);
        $stockItem->method('getMinQty')->willReturn($stockMinQty);

        $stockStatus = $this->createMock(StockStatusInterface::class);
        $stockStatus->method('getQty')->willReturn($quantity);

        $stockRegistry = $this->createMock(StockRegistryInterface::class);
        $stockRegistry->method('getStockItem')->with(7, 1)->willReturn($stockItem);
        $stockRegistry->method('getStockStatus')->with(7, 1)->willReturn($stockStatus);

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path) => $settings[$path] ?? ($path === ProductStock::DISPLAY_STATUS_PATH ? '1' : '0')
        );

        return new ProductStock($registry, $stockRegistry, $scopeConfig);
    }

    public function testAProductSoldOneAtATimeHasUnitStepAndMinimum(): void
    {
        $viewModel = $this->viewModel(false);

        $this->assertFalse($viewModel->hasIncrements());
        $this->assertSame(1, $viewModel->getStep());
        $this->assertSame(1, $viewModel->getMinQty());
    }

    public function testIncrementsBecomeTheStepAndTheStartingQuantity(): void
    {
        $viewModel = $this->viewModel(6.0);

        $this->assertTrue($viewModel->hasIncrements());
        $this->assertSame(6, $viewModel->getIncrements());
        $this->assertSame(6, $viewModel->getStep());
        $this->assertSame(6, $viewModel->getMinQty());
    }

    public function testTheMinimumRoundsUpToTheNextMultipleOfTheIncrement(): void
    {
        $this->assertSame(12, $this->viewModel(6.0, 7.0)->getMinQty());
        $this->assertSame(12, $this->viewModel(6.0, 12.0)->getMinQty());
    }

    public function testAMinimumAboveOneIsKeptWithoutIncrements(): void
    {
        $this->assertSame(3, $this->viewModel(false, 3.0)->getMinQty());
    }

    public function testFractionalIncrementsStayFractional(): void
    {
        $viewModel = $this->viewModel(0.5, 0.5);

        $this->assertSame(0.5, $viewModel->getStep());
        $this->assertSame(0.5, $viewModel->getMinQty());
    }

    public function testOffAProductPageThereAreNoIncrements(): void
    {
        $viewModel = $this->viewModel(6.0, 1.0, false);

        $this->assertFalse($viewModel->hasIncrements());
        $this->assertSame(1, $viewModel->getStep());
        $this->assertSame(1, $viewModel->getMinQty());
    }

    public function testAnAvailableProductSaysSoWithoutAQuantityWhenNoThresholdIsSet(): void
    {
        $this->assertSame(['inStock' => true, 'left' => null], $this->viewModel()->getAvailability());
    }

    public function testUnderTheThresholdTheQuantityLeftIsStated(): void
    {
        $viewModel = $this->viewModel(settings: [ProductStock::THRESHOLD_PATH => '5'], quantity: 3.0);

        $this->assertSame(['inStock' => true, 'left' => 3], $viewModel->getAvailability());
    }

    public function testTheQuantityLeftDoesNotCountTheOutOfStockThreshold(): void
    {
        $viewModel = $this->viewModel(settings: [ProductStock::THRESHOLD_PATH => '5'], quantity: 4.0, stockMinQty: 1.0);

        $this->assertSame(3, $viewModel->getAvailability()['left']);
    }

    public function testAQuantityAboveTheThresholdIsNotStated(): void
    {
        $viewModel = $this->viewModel(settings: [ProductStock::THRESHOLD_PATH => '5'], quantity: 6.0);

        $this->assertNull($viewModel->getAvailability()['left']);
    }

    public function testAThresholdOfZeroNeverStatesTheQuantity(): void
    {
        $viewModel = $this->viewModel(settings: [ProductStock::THRESHOLD_PATH => '0'], quantity: 1.0);

        $this->assertNull($viewModel->getAvailability()['left']);
    }

    public function testNothingLeftIsNotStated(): void
    {
        $viewModel = $this->viewModel(settings: [ProductStock::THRESHOLD_PATH => '5'], quantity: 0.0);

        $this->assertNull($viewModel->getAvailability()['left']);
    }

    public function testAStoreThatHidesAvailabilityGetsNothing(): void
    {
        $viewModel = $this->viewModel(settings: [ProductStock::DISPLAY_STATUS_PATH => '0']);

        $this->assertNull($viewModel->getAvailability());
    }

    public function testAProductThatCannotBeBoughtIsNotAnnouncedAsInStock(): void
    {
        $this->assertSame(['inStock' => false, 'left' => null], $this->viewModel(available: false)->getAvailability());
    }

    public function testOffAProductPageThereIsNoAvailability(): void
    {
        $this->assertNull($this->viewModel(onProductPage: false)->getAvailability());
    }
}
