<?php
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Catalog\Test\Unit\Model\Recent;

use Magento\Catalog\Model\Config as CatalogConfig;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Helper\Stock;
use MageObsidian\Catalog\Model\Recent\ProductSelection;
use PHPUnit\Framework\TestCase;

class ProductSelectionTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Collection::class)) {
            $this->markTestSkipped('Magento Catalog is not available in this runtime.');
        }
    }

    private function product(int $id): Product
    {
        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn($id);

        return $product;
    }

    private function collection(array $products): Collection
    {
        $collection = $this->createStub(Collection::class);
        foreach (
            [
                'addAttributeToSelect', 'addIdFilter', 'addAttributeToFilter', 'setVisibility',
                'addMinimalPrice', 'addFinalPrice', 'addTaxPercents', 'addUrlRewrite', 'addStoreFilter',
            ] as $method
        ) {
            $collection->method($method)->willReturnSelf();
        }
        $collection->method('getItems')->willReturn($products);

        return $collection;
    }

    private function selection(array $products = [], bool $showOutOfStock = true, ?Stock $stock = null): ProductSelection
    {
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($this->collection($products));

        $catalogConfig = $this->createStub(CatalogConfig::class);
        $catalogConfig->method('getProductAttributes')->willReturn(['name']);

        $visibility = $this->createStub(Visibility::class);
        $visibility->method('getVisibleInCatalogIds')->willReturn([2, 4]);

        $stockConfiguration = $this->createStub(StockConfigurationInterface::class);
        $stockConfiguration->method('isShowOutOfStock')->willReturn($showOutOfStock);

        return new ProductSelection(
            $factory,
            $catalogConfig,
            $visibility,
            $stockConfiguration,
            $stock ?? $this->createStub(Stock::class)
        );
    }

    public function testIdsKeepOnlyPositiveIntegersWithoutRepeats(): void
    {
        $ids = $this->selection()->idsFrom('4, 9,abc,-3,0,4,1.5,,12');

        $this->assertSame([4, 9, 12], $ids);
    }

    public function testIdsAreCapped(): void
    {
        $raw = implode(',', range(1, 40));

        $this->assertCount(ProductSelection::MAX_IDS, $this->selection()->idsFrom($raw));
    }

    public function testNothingIsLoadedForNoIds(): void
    {
        $this->assertSame([], $this->selection([$this->product(1)])->load([], 5));
    }

    public function testProductsComeBackInTheOrderAsked(): void
    {
        $selection = $this->selection([$this->product(1), $this->product(2), $this->product(3)]);

        $loaded = $selection->load([3, 1, 2], 5);

        $this->assertSame([3, 1, 2], array_map(static fn(Product $product): int => (int)$product->getId(), $loaded));
    }

    public function testAProductTheStoreNoLongerOffersIsSkipped(): void
    {
        $selection = $this->selection([$this->product(1), $this->product(3)]);

        $loaded = $selection->load([3, 2, 1], 5);

        $this->assertSame([3, 1], array_map(static fn(Product $product): int => (int)$product->getId(), $loaded));
    }

    public function testNoMoreThanTheCountAreReturned(): void
    {
        $selection = $this->selection([$this->product(1), $this->product(2), $this->product(3)]);

        $this->assertCount(2, $selection->load([1, 2, 3], 2));
    }

    public function testOutOfStockProductsAreFilteredOnlyWhenTheStoreHidesThem(): void
    {
        $hiding = $this->createMock(Stock::class);
        $hiding->expects($this->once())->method('addIsInStockFilterToCollection');
        $this->selection([$this->product(1)], false, $hiding)->load([1], 5);

        $showing = $this->createMock(Stock::class);
        $showing->expects($this->never())->method('addIsInStockFilterToCollection');
        $this->selection([$this->product(1)], true, $showing)->load([1], 5);
    }
}
