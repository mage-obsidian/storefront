<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\ViewModel;

use Magento\Catalog\Helper\Output as OutputHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Framework\Pricing\Amount\AmountInterface;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Pricing\PriceInfoInterface;
use Magento\Framework\Registry;
use Magento\Framework\Url\Helper\Data as UrlHelper;
use Magento\Framework\UrlInterface;
use MageObsidian\Storefront\ViewModel\ProductView;
use PHPUnit\Framework\TestCase;

/**
 * Buy-box ViewModel for the PDP. We assert the decisions the template relies on:
 * the quick-add vs. options discriminator (canConfigure), the sale test
 * (regular > final), the configurable flag, the WYSIWYG-filtered description and
 * graceful degradation off a product page. Needs Magento Catalog/Pricing types,
 * so it runs in a Magento root (see phpunit.ci.xml).
 */
class ProductViewTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Product::class)) {
            $this->markTestSkipped('Magento Catalog is not available in this runtime.');
        }
    }

    private function viewModel(?Product $product, ?OutputHelper $output = null): ProductView
    {
        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->with('current_product')->willReturn($product);

        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturn('https://shop.test/checkout/cart/add');

        $urlHelper = $this->createMock(UrlHelper::class);
        $urlHelper->method('getEncodedUrl')->willReturn('ENC');

        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->method('format')->willReturn('$10.00');

        return new ProductView(
            $registry,
            $output ?? $this->createMock(OutputHelper::class),
            $url,
            $urlHelper,
            $priceCurrency
        );
    }

    private function product(string $typeId, bool $canConfigure): Product
    {
        $type = $this->createMock(AbstractType::class);
        $type->method('canConfigure')->willReturn($canConfigure);

        $product = $this->createMock(Product::class);
        $product->method('getTypeId')->willReturn($typeId);
        $product->method('getTypeInstance')->willReturn($type);

        return $product;
    }

    private function priceInfo(float $final, float $regular): PriceInfoInterface
    {
        $finalPrice = $this->createMock(PriceInterface::class);
        $finalPrice->method('getAmount')->willReturn($this->amount($final));
        $regularPrice = $this->createMock(PriceInterface::class);
        $regularPrice->method('getAmount')->willReturn($this->amount($regular));

        $info = $this->createMock(PriceInfoInterface::class);
        $info->method('getPrice')->willReturnMap([
            ['final_price', $finalPrice],
            ['regular_price', $regularPrice],
        ]);

        return $info;
    }

    private function amount(float $value): AmountInterface
    {
        $amount = $this->createMock(AmountInterface::class);
        $amount->method('getValue')->willReturn($value);

        return $amount;
    }

    public function testNoProductDegradesGracefully(): void
    {
        $view = $this->viewModel(null);

        $this->assertSame('', $view->getName());
        $this->assertSame('', $view->getSku());
        $this->assertFalse($view->isSaleable());
        $this->assertFalse($view->needsOptions());
        $this->assertSame(0, $view->getProductId());
        $this->assertSame('', $view->getDescriptionHtml());
    }

    public function testSimpleProductNeedsNoOptions(): void
    {
        $this->assertFalse($this->viewModel($this->product('simple', false))->needsOptions());
    }

    public function testConfigurableProductNeedsOptionsAndIsConfigurable(): void
    {
        $view = $this->viewModel($this->product('configurable', true));

        $this->assertTrue($view->needsOptions());
        $this->assertTrue($view->isConfigurable());
    }

    public function testOnSaleWhenRegularExceedsFinal(): void
    {
        $product = $this->product('simple', false);
        $product->method('getPriceInfo')->willReturn($this->priceInfo(8.0, 10.0));

        $view = $this->viewModel($product);

        $this->assertTrue($view->isOnSale());
        $this->assertSame(8.0, $view->getFinalPrice());
        $this->assertSame(10.0, $view->getRegularPrice());
    }

    public function testNotOnSaleWhenPricesMatch(): void
    {
        $product = $this->product('simple', false);
        $product->method('getPriceInfo')->willReturn($this->priceInfo(10.0, 10.0));

        $this->assertFalse($this->viewModel($product)->isOnSale());
    }

    public function testDescriptionRunsThroughOutputFilter(): void
    {
        $product = $this->product('simple', false);
        $product->method('getData')->with('description')->willReturn('<p>Cut clean.</p>');

        $output = $this->createMock(OutputHelper::class);
        $output->expects($this->once())
            ->method('productAttribute')
            ->with($product, '<p>Cut clean.</p>', 'description')
            ->willReturn('<p>Cut clean.</p>');

        $this->assertSame('<p>Cut clean.</p>', $this->viewModel($product, $output)->getDescriptionHtml());
    }
}
