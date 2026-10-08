<?php
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\ViewModel;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Helper\Product\Configuration\ConfigurationInterface;
use Magento\Catalog\Helper\Product\ConfigurationPool;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Tax\Model\Config as TaxConfig;
use Magento\Weee\Helper\Data as WeeeHelper;
use MageObsidian\Checkout\ViewModel\CartItems;
use MageObsidian\Checkout\ViewModel\TaxDisplay;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $type = $this->createMock(AbstractType::class);
        $type->method('canConfigure')->willReturn(true);
        $product = $this->createMock(Product::class);
        $product->method('getProductUrl')->willReturn('https://shop.test/chaz.html');
        $product->method('getId')->willReturn(42);
        $product->method('getTypeInstance')->willReturn($type);

        $item = $this->getMockBuilder(QuoteItem::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getItemId', 'getName', 'getQty', 'getCalculationPrice', 'getProduct', 'getProductType'])
            ->getMock();
        $item->method('getItemId')->willReturn(15);
        $item->method('getName')->willReturn('Chaz Hoodie');
        $item->method('getQty')->willReturn(2.0);
        $item->method('getCalculationPrice')->willReturn(52.0);
        $item->setData(['sku' => 'MH01-M-Gray', 'row_total' => 104.0, 'row_total_incl_tax' => 112.32, 'price_incl_tax' => 56.16]);
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
            'sku' => 'MH01-M-Gray',
            'url' => 'https://shop.test/chaz.html',
            'image' => 'https://shop.test/media/chaz.jpg',
            'qty' => 2.0,
            'price' => '$52.00',
            'rowTotal' => '$104.00',
            'prices' => ['excl' => '$52.00'],
            'rowTotals' => ['excl' => '$104.00'],
            'fixedTaxes' => [],
            'options' => [
                ['label' => 'Size', 'value' => 'M'],
                ['label' => 'Color', 'value' => 'Gray'],
            ],
            'configureUrl' => 'https://shop.test/checkout/cart/configure/id/15/product_id/42/',
            'messages' => [],
        ], $rows[0]);
    }

    public static function priceModes(): array
    {
        return [
            'including tax' => [
                ['displayCartPricesInclTax' => true],
                ['price' => '$56.16', 'rowTotal' => '$112.32', 'prices' => ['incl' => '$56.16'], 'rowTotals' => ['incl' => '$112.32']],
            ],
            'excluding tax' => [
                [],
                ['price' => '$52.00', 'rowTotal' => '$104.00', 'prices' => ['excl' => '$52.00'], 'rowTotals' => ['excl' => '$104.00']],
            ],
            'both' => [
                ['displayCartPricesBoth' => true],
                [
                    'price' => '$52.00',
                    'rowTotal' => '$104.00',
                    'prices' => ['incl' => '$56.16', 'excl' => '$52.00'],
                    'rowTotals' => ['incl' => '$112.32', 'excl' => '$104.00'],
                ],
            ],
        ];
    }

    #[DataProvider('priceModes')]
    public function testAmountsOfALineShareOneTaxBasis(array $tax, array $expected): void
    {
        $item = $this->pricedItem(2.0, 52.0, ['row_total' => 104.0, 'row_total_incl_tax' => 112.32, 'price_incl_tax' => 56.16]);

        $row = $this->viewModel([$item], null, $this->taxDisplay($tax))->getItems()[0];

        foreach ($expected as $key => $value) {
            $this->assertSame($value, $row[$key], $key);
        }
    }

    public function testFallsBackToThePriceWithoutTaxWhenTaxWasNotCollected(): void
    {
        $item = $this->pricedItem(1.0, 36.0, ['row_total' => 36.0]);

        $row = $this->viewModel([$item], null, $this->taxDisplay(['displayCartPricesInclTax' => true]))->getItems()[0];

        $this->assertSame('$36.00', $row['price']);
        $this->assertSame('$36.00', $row['rowTotal']);
    }

    public function testFixedProductTaxJoinsThePriceWhenTheStoreIncludesIt(): void
    {
        $item = $this->pricedItem(1.0, 32.0, ['row_total' => 32.0, 'row_total_incl_tax' => 34.56, 'price_incl_tax' => 34.56]);
        $weee = ['type' => 0, 'rowExcl' => 5.0, 'rowIncl' => 5.4];

        $row = $this->viewModel([$item], null, $this->taxDisplay([], $weee))->getItems()[0];

        $this->assertSame('$37.00', $row['price']);
        $this->assertSame('$37.00', $row['rowTotal']);
        $this->assertSame([], $row['fixedTaxes']);

        $row = $this->viewModel([$item], null, $this->taxDisplay(['displayCartPricesInclTax' => true], $weee))->getItems()[0];

        $this->assertSame('$39.96', $row['price']);
        $this->assertSame('$39.96', $row['rowTotal']);
    }

    public function testFixedProductTaxStaysOutOfThePriceWhenTheStoreExcludesIt(): void
    {
        $item = $this->pricedItem(1.0, 32.0, ['row_total' => 32.0, 'row_total_incl_tax' => 34.56]);

        $row = $this->viewModel([$item], null, $this->taxDisplay([], ['type' => 3, 'rowExcl' => 5.0, 'rowIncl' => 5.4]))->getItems()[0];

        $this->assertSame('$32.00', $row['price']);
        $this->assertSame([], $row['fixedTaxes']);
    }

    public function testNamesEachFixedProductTaxWhenTheStoreDescribesThem(): void
    {
        $item = $this->pricedItem(1.0, 32.0, ['row_total' => 32.0, 'row_total_incl_tax' => 34.56]);
        $weee = [
            'type' => 1,
            'rowExcl' => 5.0,
            'rowIncl' => 5.4,
            'applied' => [
                ['title' => 'Recycling', 'row_amount' => 3.0, 'row_amount_incl_tax' => 3.24],
                ['title' => 'Battery', 'row_amount' => 2.0, 'row_amount_incl_tax' => 2.16],
            ],
        ];

        $row = $this->viewModel([$item], null, $this->taxDisplay([], $weee))->getItems()[0];

        $this->assertSame(
            [['label' => 'Recycling', 'amount' => '$3.00'], ['label' => 'Battery', 'amount' => '$2.00']],
            $row['fixedTaxes']
        );

        $row = $this->viewModel([$item], null, $this->taxDisplay(['displayCartPricesInclTax' => true], $weee))->getItems()[0];

        $this->assertSame(
            [['label' => 'Recycling', 'amount' => '$3.24'], ['label' => 'Battery', 'amount' => '$2.16']],
            $row['fixedTaxes']
        );
    }

    public function testCarriesTheQuoteMessagesOfTheLine(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getTypeInstance')->willReturn($this->createMock(AbstractType::class));

        $item = $this->getMockBuilder(QuoteItem::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getItemId', 'getName', 'getQty', 'getCalculationPrice', 'getProduct', 'getProductType', 'getMessage'])
            ->getMock();
        $item->method('getItemId')->willReturn(9);
        $item->method('getName')->willReturn('Driven Backpack');
        $item->method('getQty')->willReturn(3.0);
        $item->method('getCalculationPrice')->willReturn(36.0);
        $item->method('getProduct')->willReturn($product);
        $item->method('getProductType')->willReturn('simple');
        $item->method('getMessage')->with(false)->willReturn(['Only 2 left', '', 'Price changed']);

        $rows = $this->viewModel([$item])->getItems();

        $this->assertSame(['Only 2 left', 'Price changed'], $rows[0]['messages']);
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
    private function viewModel(array $items, ?ConfigurationPool $pool = null, ?TaxDisplay $taxDisplay = null): CartItems
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

        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn (string $route, array $params): string =>
                "https://shop.test/$route/id/{$params['id']}/product_id/{$params['product_id']}/"
        );

        return new CartItems(
            $session,
            $pool ?? $this->createMock(ConfigurationPool::class),
            $image,
            $priceCurrency,
            $url,
            $taxDisplay ?? $this->taxDisplay()
        );
    }

    private function pricedItem(float $qty, float $calculationPrice, array $data): QuoteItem
    {
        $product = $this->createMock(Product::class);
        $product->method('getTypeInstance')->willReturn($this->createMock(AbstractType::class));

        $item = $this->getMockBuilder(QuoteItem::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getItemId', 'getName', 'getQty', 'getCalculationPrice', 'getProduct', 'getProductType'])
            ->getMock();
        $item->method('getItemId')->willReturn(7);
        $item->method('getName')->willReturn('Strive Shoulder Pack');
        $item->method('getQty')->willReturn($qty);
        $item->method('getCalculationPrice')->willReturn($calculationPrice);
        $item->setData($data);
        $item->method('getProduct')->willReturn($product);
        $item->method('getProductType')->willReturn('simple');

        return $item;
    }

    private function taxDisplay(array $tax = [], array $weee = []): TaxDisplay
    {
        $config = $this->createMock(TaxConfig::class);
        foreach (['displayCartPricesInclTax', 'displayCartPricesBoth'] as $method) {
            $config->method($method)->willReturn($tax[$method] ?? false);
        }

        $helper = $this->createMock(WeeeHelper::class);
        $helper->method('isEnabled')->willReturn(isset($weee['type']));
        $helper->method('typeOfDisplay')->willReturn($weee['type'] ?? 3);
        $helper->method('getWeeeTaxAppliedRowAmount')->willReturn($weee['rowExcl'] ?? 0.0);
        $helper->method('getRowWeeeTaxInclTax')->willReturn($weee['rowIncl'] ?? 0.0);
        $helper->method('getApplied')->willReturn($weee['applied'] ?? []);

        return new TaxDisplay($config, $helper);
    }
}
