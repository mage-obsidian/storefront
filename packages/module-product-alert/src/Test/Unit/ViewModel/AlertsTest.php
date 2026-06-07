<?php
declare(strict_types=1);

namespace MageObsidian\ProductAlert\Test\Unit\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Magento\ProductAlert\Helper\Data as ProductAlertHelper;
use MageObsidian\ProductAlert\ViewModel\Alerts;
use PHPUnit\Framework\TestCase;

/**
 * PDP product-alert VM. We assert each notify link self-gates on the native
 * enable flag plus product applicability (price needs a shown price; stock needs
 * an out-of-stock product) and that the signup URLs come from the helper.
 */
class AlertsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(ProductAlertHelper::class) || !class_exists(Product::class)) {
            $this->markTestSkipped('Magento ProductAlert/Catalog is not available in this runtime.');
        }
    }

    private function product(bool $canShowPrice, bool $available): Product
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->addMethods(['getCanShowPrice'])
            ->onlyMethods(['getId', 'isAvailable'])
            ->getMock();
        $product->method('getId')->willReturn(6);
        $product->method('getCanShowPrice')->willReturn($canShowPrice);
        $product->method('isAvailable')->willReturn($available);

        return $product;
    }

    private function make(?Product $product, ?ProductAlertHelper $helper = null): Alerts
    {
        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->with('current_product')->willReturn($product);

        return new Alerts($helper ?? $this->createMock(ProductAlertHelper::class), $registry);
    }

    public function testPriceAllowedWhenEnabledAndPriceShown(): void
    {
        $helper = $this->createMock(ProductAlertHelper::class);
        $helper->method('isPriceAlertAllowed')->willReturn(true);

        $this->assertTrue($this->make($this->product(true, true), $helper)->isPriceAllowed());
    }

    public function testPriceNotAllowedWhenPriceHidden(): void
    {
        $helper = $this->createMock(ProductAlertHelper::class);
        $helper->method('isPriceAlertAllowed')->willReturn(true);

        $this->assertFalse($this->make($this->product(false, true), $helper)->isPriceAllowed());
    }

    public function testStockAllowedOnlyWhenOutOfStock(): void
    {
        $helper = $this->createMock(ProductAlertHelper::class);
        $helper->method('isStockAlertAllowed')->willReturn(true);

        $this->assertTrue($this->make($this->product(true, false), $helper)->isStockAllowed());
        $this->assertFalse($this->make($this->product(true, true), $helper)->isStockAllowed());
    }

    public function testNotAllowedOffAProductPage(): void
    {
        $view = $this->make(null);

        $this->assertFalse($view->isPriceAllowed());
        $this->assertFalse($view->isStockAllowed());
    }

    public function testSignupUrlsComeFromTheHelper(): void
    {
        $helper = $this->createMock(ProductAlertHelper::class);
        $helper->method('getSaveUrl')->willReturnMap([
            ['price', 'https://shop.test/productalert/add/price'],
            ['stock', 'https://shop.test/productalert/add/stock'],
        ]);

        $view = $this->make($this->product(true, true), $helper);

        $this->assertSame('https://shop.test/productalert/add/price', $view->getPriceUrl());
        $this->assertSame('https://shop.test/productalert/add/stock', $view->getStockUrl());
    }
}
