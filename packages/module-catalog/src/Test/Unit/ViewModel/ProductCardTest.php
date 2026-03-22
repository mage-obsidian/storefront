<?php
declare(strict_types=1);

namespace MageObsidian\Catalog\Test\Unit\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Checkout\Helper\Cart as CartHelper;
use Magento\Framework\Url\Helper\Data as UrlHelper;
use MageObsidian\Catalog\ViewModel\ProductCard;
use PHPUnit\Framework\TestCase;

/**
 * Per-product presentation logic for the reusable product card. Keeps the type
 * decision (quick-add vs choose-options) out of the template and testable: a
 * product is quick-addable only when it is saleable AND needs no configuration
 * (canConfigure() === false — plain simple/virtual). Composite types and
 * options-bearing products route to the PDP. Needs Magento Catalog types, so it
 * runs in a Magento root (see phpunit.ci.xml).
 */
class ProductCardTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Product::class)) {
            $this->markTestSkipped('Magento Catalog is not available in this runtime.');
        }
    }

    private function card(?CartHelper $cartHelper = null, ?UrlHelper $urlHelper = null): ProductCard
    {
        return new ProductCard(
            $cartHelper ?? $this->createMock(CartHelper::class),
            $urlHelper ?? $this->createMock(UrlHelper::class)
        );
    }

    private function product(bool $saleable, bool $canConfigure): Product
    {
        $type = $this->createMock(AbstractType::class);
        $type->method('canConfigure')->willReturn($canConfigure);

        $product = $this->createMock(Product::class);
        $product->method('isSaleable')->willReturn($saleable);
        $product->method('getTypeInstance')->willReturn($type);

        return $product;
    }

    public function testSimpleSaleableProductIsQuickAdd(): void
    {
        // Plain simple/virtual: saleable and nothing to configure.
        $this->assertTrue($this->card()->isQuickAdd($this->product(true, false)));
    }

    public function testConfigurableProductIsNotQuickAdd(): void
    {
        // Configurable/bundle/grouped (or a simple with required options) →
        // choose options on the PDP instead. canConfigure() is true even though
        // Configurable::isPossibleBuyFromList() would lie and return true.
        $this->assertFalse($this->card()->isQuickAdd($this->product(true, true)));
    }

    public function testOutOfStockProductIsNotQuickAdd(): void
    {
        $this->assertFalse($this->card()->isQuickAdd($this->product(false, false)));
    }

    public function testAddToCartPostParamsBuildActionAndData(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn(42);

        $cartHelper = $this->createMock(CartHelper::class);
        $cartHelper->method('getAddUrl')->willReturn('https://shop.test/checkout/cart/add/product/42/');
        $urlHelper = $this->createMock(UrlHelper::class);
        $urlHelper->method('getEncodedUrl')->willReturn('ENC');

        $params = $this->card($cartHelper, $urlHelper)->getAddToCartPostParams($product);

        $this->assertSame('https://shop.test/checkout/cart/add/product/42/', $params['action']);
        $this->assertSame(42, $params['data']['product']);
        $this->assertSame('ENC', $params['data']['uenc']);
    }

    public function testAddToCartPostParamsIsEmptyWhenUrlBuildFails(): void
    {
        $cartHelper = $this->createMock(CartHelper::class);
        $cartHelper->method('getAddUrl')->willThrowException(new \RuntimeException('boom'));

        $this->assertSame([], $this->card($cartHelper)->getAddToCartPostParams($this->createMock(Product::class)));
    }
}
