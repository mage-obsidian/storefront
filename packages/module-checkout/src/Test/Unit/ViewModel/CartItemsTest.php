<?php
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\ViewModel;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Helper\Product\Configuration\ConfigurationInterface;
use Magento\Catalog\Helper\Product\ConfigurationPool;
use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use MageObsidian\Checkout\ViewModel\CartItems;
use PHPUnit\Framework\TestCase;

/**
 * Normalises the session quote's visible items into render-ready rows. We assert
 * the mapping (thumbnail, options, unit price, row total) and graceful empties;
 * needs Magento Catalog/Quote types, so it runs in a Magento root.
 */
class CartItemsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Product::class)) {
            $this->markTestSkipped('Magento Catalog is not available in this runtime.');
        }
    }

    public function testMapsVisibleQuoteItemsToRows(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getProductUrl')->willReturn('https://shop.test/chaz.html');

        $item = $this->getMockBuilder(QuoteItem::class)
            ->disableOriginalConstructor()
            ->addMethods(['getRowTotalInclTax', 'getRowTotal'])
            ->onlyMethods(['getItemId', 'getName', 'getQty', 'getCalculationPrice', 'getProduct', 'getProductType'])
            ->getMock();
        $item->method('getItemId')->willReturn(15);
        $item->method('getName')->willReturn('Chaz Hoodie');
        $item->method('getQty')->willReturn(2.0);
        $item->method('getCalculationPrice')->willReturn(52.0);
        $item->method('getRowTotalInclTax')->willReturn(104.0);
        $item->method('getProduct')->willReturn($product);
        $item->method('getProductType')->willReturn('configurable');

        $configuration = $this->createMock(ConfigurationInterface::class);
        $configuration->method('getOptions')->with($item)->willReturn([
            ['label' => 'Size', 'value' => 'M'],
            ['label' => 'Color', 'value' => ['Gray']],
        ]);
        $pool = $this->createMock(ConfigurationPool::class);
        $pool->method('getByProductType')->with('configurable')->willReturn($configuration);

        $rows = $this->viewModel([$item], $pool)->getItems();

        $this->assertCount(1, $rows);
        $this->assertSame([
            'id' => 15,
            'name' => 'Chaz Hoodie',
            'url' => 'https://shop.test/chaz.html',
            'image' => 'https://shop.test/media/chaz.jpg',
            'qty' => 2.0,
            'price' => '$52.00',
            'rowTotal' => '$104.00',
            'options' => [
                ['label' => 'Size', 'value' => 'M'],
                ['label' => 'Color', 'value' => 'Gray'],
            ],
        ], $rows[0]);
    }

    public function testReturnsEmptyListForAnEmptyCart(): void
    {
        $this->assertSame([], $this->viewModel([])->getItems());
    }

    /**
     * @param array<int, QuoteItem> $items
     * @param ConfigurationPool|null $pool
     * @return CartItems
     */
    private function viewModel(array $items, ?ConfigurationPool $pool = null): CartItems
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getAllVisibleItems')->willReturn($items);
        $session = $this->createMock(CheckoutSession::class);
        $session->method('getQuote')->willReturn($quote);

        $image = $this->createMock(ImageHelper::class);
        $image->method('init')->willReturnSelf();
        $image->method('getUrl')->willReturn('https://shop.test/media/chaz.jpg');

        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->method('format')->willReturnCallback(
            static fn ($amount): string => '$' . number_format((float)$amount, 2)
        );

        return new CartItems(
            $session,
            $pool ?? $this->createMock(ConfigurationPool::class),
            $image,
            $priceCurrency
        );
    }
}
