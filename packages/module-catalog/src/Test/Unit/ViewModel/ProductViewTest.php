<?php
declare(strict_types=1);

namespace MageObsidian\Catalog\Test\Unit\ViewModel;

use Magento\Catalog\Helper\Output as OutputHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Framework\Pricing\Amount\AmountInterface;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Pricing\PriceInfoInterface;
use Magento\Framework\Pricing\Render as PriceRender;
use Magento\Framework\Registry;
use Magento\Framework\Url\Helper\Data as UrlHelper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\LayoutInterface;
use MageObsidian\Catalog\ViewModel\ProductView;
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

    private function viewModel(
        ?Product $product,
        ?OutputHelper $output = null,
        ?LayoutInterface $layout = null
    ): ProductView {
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
            $priceCurrency,
            $layout ?? $this->createMock(LayoutInterface::class)
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

    public function testDownloadableProductIsDownloadable(): void
    {
        $view = $this->viewModel($this->product('downloadable', false));

        $this->assertTrue($view->isDownloadable());
        $this->assertFalse($view->isConfigurable());
    }

    public function testGroupedProductIsGrouped(): void
    {
        $view = $this->viewModel($this->product('grouped', true));

        $this->assertTrue($view->isGrouped());
        $this->assertFalse($view->isDownloadable());
    }

    public function testBundleProductIsBundle(): void
    {
        $view = $this->viewModel($this->product('bundle', true));

        $this->assertTrue($view->isBundle());
        $this->assertFalse($view->isGrouped());
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

    public function testOptionYearsSpanCurrentYearThroughPlusTwenty(): void
    {
        $years = $this->viewModel($this->product('simple', false))->getOptionYears();

        $this->assertCount(21, $years);
        $this->assertSame((int)date('Y'), $years[0]);
        $this->assertSame((int)date('Y') + 20, $years[20]);
    }

    public function testCurrencyFormatComesFromTheStoreCurrency(): void
    {
        $currency = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getOutputFormat'])
            ->getMock();
        $currency->method('getOutputFormat')->willReturn('$%s');

        $registry = $this->createMock(Registry::class);
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->method('getCurrency')->willReturn($currency);

        $view = new ProductView(
            $registry,
            $this->createMock(OutputHelper::class),
            $this->createMock(UrlInterface::class),
            $this->createMock(UrlHelper::class),
            $priceCurrency,
            $this->createMock(LayoutInterface::class)
        );

        $this->assertSame('$%s', $view->getCurrencyFormat());
    }

    public function testPriceHtmlRendersFinalPriceThroughTheRenderBlock(): void
    {
        $product = $this->product('simple', false);

        $priceRender = $this->createMock(PriceRender::class);
        $priceRender->expects($this->once())
            ->method('render')
            ->with('final_price', $product, $this->arrayHasKey('zone'))
            ->willReturn('<span class="price">$8.00</span>');

        $layout = $this->createMock(LayoutInterface::class);
        $layout->method('getBlock')->with('product.price.render.default')->willReturn($priceRender);

        $view = $this->viewModel($product, null, $layout);

        $this->assertSame('<span class="price">$8.00</span>', $view->getPriceHtml());
    }

    public function testPriceHtmlIsEmptyWithoutTheRenderBlock(): void
    {
        $layout = $this->createMock(LayoutInterface::class);
        $layout->method('getBlock')->willReturn(null);

        $view = $this->viewModel($this->product('simple', false), null, $layout);

        $this->assertSame('', $view->getPriceHtml());
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
