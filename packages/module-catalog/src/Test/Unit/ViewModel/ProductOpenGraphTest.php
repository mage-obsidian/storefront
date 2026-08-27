<?php
declare(strict_types=1);

namespace MageObsidian\Catalog\Test\Unit\ViewModel;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\Pricing\Amount\AmountInterface;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\PriceInfoInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Catalog\ViewModel\ProductOpenGraph;
use PHPUnit\Framework\TestCase;

/**
 * What a shared product link previews as. Needs Magento Catalog/Pricing types,
 * so it runs in a Magento root (see phpunit.ci.xml).
 */
class ProductOpenGraphTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Product::class)) {
            $this->markTestSkipped('Magento Catalog is not available in this runtime.');
        }
    }

    private function viewModel(?Product $product, string $imageUrl = 'https://shop.test/media/bag.jpg'): ProductOpenGraph
    {
        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->with('current_product')->willReturn($product);

        $image = $this->createMock(ImageHelper::class);
        $image->method('init')->willReturnSelf();
        $image->method('getUrl')->willReturn($imageUrl);

        $store = $this->createMock(Store::class);
        $store->method('getCurrentCurrencyCode')->willReturn('USD');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new ProductOpenGraph($registry, $image, $storeManager, $this->createMock(CurrencyFactory::class));
    }

    private function product(?float $finalPrice, string $shortDescription = ''): Product
    {
        $amount = $this->createMock(AmountInterface::class);
        $amount->method('getValue')->willReturn($finalPrice);

        $price = $this->createMock(PriceInterface::class);
        $price->method('getAmount')->willReturn($amount);

        $priceInfo = $this->createMock(PriceInfoInterface::class);
        $priceInfo->method('getPrice')->willReturn($price);

        $product = $this->createMock(Product::class);
        $product->method('getName')->willReturn('Joust <b>Duffle</b> Bag');
        $product->method('getProductUrl')->willReturn('https://shop.test/joust-duffle-bag.html');
        $product->method('getPriceInfo')->willReturn($priceInfo);
        $product->method('getData')->willReturnCallback(
            static fn (string $key) => $key === 'short_description' ? $shortDescription : null
        );

        return $product;
    }

    public function testItDescribesTheProductToAPreview(): void
    {
        $properties = $this->viewModel($this->product(199.0, "  A <em>roomy</em>\n bag.  "))->getProperties();

        $this->assertSame([
            'og:type' => 'product',
            'og:title' => 'Joust Duffle Bag',
            'og:url' => 'https://shop.test/joust-duffle-bag.html',
            'og:image' => 'https://shop.test/media/bag.jpg',
            'og:description' => 'A roomy bag.',
            'product:price:amount' => '199.00',
            'product:price:currency' => 'USD',
        ], $properties);
    }

    public function testAPropertyWithNothingBehindItIsAbsentRatherThanEmpty(): void
    {
        $properties = $this->viewModel($this->product(null), '')->getProperties();

        $this->assertArrayNotHasKey('og:description', $properties);
        $this->assertArrayNotHasKey('product:price:amount', $properties);
        $this->assertArrayNotHasKey('product:price:currency', $properties);
        $this->assertSame('product', $properties['og:type']);
    }

    public function testAnImageThatCannotBeResolvedIsLeftOut(): void
    {
        $properties = $this->viewModel($this->product(10.0), '')->getProperties();

        $this->assertArrayNotHasKey('og:image', $properties);
    }

    public function testItSaysNothingOffAProductPage(): void
    {
        $this->assertSame([], $this->viewModel(null)->getProperties());
    }
}
