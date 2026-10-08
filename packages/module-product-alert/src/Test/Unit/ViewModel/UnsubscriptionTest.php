<?php
/**
 * This file is part of the MageObsidian - ProductAlert project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ProductAlert\Test\Unit\ViewModel;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Session;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\ProductAlert\Model\Stock;
use Magento\ProductAlert\Model\StockFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\ProductAlert\ViewModel\Unsubscription;
use PHPUnit\Framework\TestCase;

class UnsubscriptionTest extends TestCase
{
    private array $applied = [];

    protected function setUp(): void
    {
        if (!class_exists(Stock::class) || !class_exists(Session::class)) {
            $this->markTestSkipped('Magento ProductAlert is not available in this runtime.');
        }
        $this->applied = [];
    }

    private function subscription(?int $id): Stock
    {
        $stock = $this->createStub(Stock::class);
        $stock->method('__call')->willReturnCallback(function (string $method, array $arguments) use ($stock): Stock {
            $this->applied[$method] = $arguments[0] ?? null;

            return $stock;
        });
        $stock->method('loadByParam')->willReturn($stock);
        $stock->method('getId')->willReturn($id);

        return $stock;
    }

    private function subject(?int $subscriptionId, ?ProductRepositoryInterface $products = null): Unsubscription
    {
        $factory = $this->createStub(StockFactory::class);
        $factory->method('create')->willReturn($this->subscription($subscriptionId));

        $session = $this->createStub(Session::class);
        $session->method('getCustomerId')->willReturn(11);

        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $store->method('getWebsiteId')->willReturn(1);
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);

        return new Unsubscription($factory, $session, $stores, $products ?? $this->createStub(ProductRepositoryInterface::class));
    }

    public function testASubscriptionTheCustomerHoldsIsFound(): void
    {
        $this->assertTrue($this->subject(7)->hasSubscription(42));
        $this->assertSame(11, $this->applied['setCustomerId']);
        $this->assertSame(42, $this->applied['setProductId']);
        $this->assertSame(1, $this->applied['setWebsiteId']);
        $this->assertSame(1, $this->applied['setStoreId']);
    }

    public function testNoSubscriptionMeansTheLinkIsStale(): void
    {
        $this->assertFalse($this->subject(null)->hasSubscription(42));
    }

    public function testAnEmptyProductIsNeverASubscription(): void
    {
        $this->assertFalse($this->subject(7)->hasSubscription(0));
    }

    public function testTheProductNameComesFromTheCatalogue(): void
    {
        $product = $this->createStub(Product::class);
        $product->method('getName')->willReturn('Hoodie');
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('getById')->willReturn($product);

        $this->assertSame('Hoodie', $this->subject(7, $products)->getProductName(42));
    }

    public function testAMissingProductHasNoName(): void
    {
        $products = $this->createStub(ProductRepositoryInterface::class);
        $products->method('getById')->willThrowException(new NoSuchEntityException());

        $this->assertSame('', $this->subject(7, $products)->getProductName(42));
    }
}
