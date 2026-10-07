<?php
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\ViewModel;

use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Tax\Model\Config as TaxConfig;
use Magento\Weee\Helper\Data as WeeeHelper;
use MageObsidian\Checkout\ViewModel\TaxDisplay;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TaxDisplayTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(TaxConfig::class) || !class_exists(WeeeHelper::class)) {
            $this->markTestSkipped('Magento Tax and Weee are not available in this runtime.');
        }
    }

    public static function modes(): array
    {
        return [
            'including' => [['incl' => true, 'both' => false], 'incl'],
            'excluding' => [['incl' => false, 'both' => false], 'excl'],
            'both' => [['incl' => false, 'both' => true], 'both'],
        ];
    }

    #[DataProvider('modes')]
    public function testReadsThePriceMode(array $flags, string $expected): void
    {
        $viewModel = $this->viewModel(['displayCartPricesInclTax' => $flags['incl'], 'displayCartPricesBoth' => $flags['both']]);

        $this->assertSame($expected, $viewModel->priceMode());
    }

    #[DataProvider('modes')]
    public function testReadsTheSubtotalMode(array $flags, string $expected): void
    {
        $viewModel = $this->viewModel(['displayCartSubtotalInclTax' => $flags['incl'], 'displayCartSubtotalBoth' => $flags['both']]);

        $this->assertSame($expected, $viewModel->subtotalMode());
    }

    #[DataProvider('modes')]
    public function testReadsTheShippingMode(array $flags, string $expected): void
    {
        $viewModel = $this->viewModel(['displayCartShippingInclTax' => $flags['incl'], 'displayCartShippingBoth' => $flags['both']]);

        $this->assertSame($expected, $viewModel->shippingMode());
    }

    public static function fixedTaxModes(): array
    {
        return [
            'disabled' => [false, 0, 'off'],
            'including' => [true, 0, 'incl'],
            'including with descriptions' => [true, 1, 'incl_desc'],
            'excluding with descriptions and the final price' => [true, 2, 'excl_desc_incl'],
            'excluding' => [true, 3, 'excl'],
        ];
    }

    #[DataProvider('fixedTaxModes')]
    public function testReadsTheFixedTaxMode(bool $enabled, int $type, string $expected): void
    {
        $viewModel = $this->viewModel([], ['isEnabled' => $enabled, 'type' => $type]);

        $this->assertSame($expected, $viewModel->fixedTaxMode());
    }

    public function testFixedTaxIsPartOfThePriceOnlyInTheIncludingModes(): void
    {
        $this->assertTrue($this->viewModel([], ['type' => 0])->includesFixedTaxInPrice());
        $this->assertTrue($this->viewModel([], ['type' => 1])->includesFixedTaxInPrice());
        $this->assertFalse($this->viewModel([], ['type' => 2])->includesFixedTaxInPrice());
        $this->assertFalse($this->viewModel([], ['type' => 3])->includesFixedTaxInPrice());
        $this->assertFalse($this->viewModel([], ['isEnabled' => false, 'type' => 0])->includesFixedTaxInPrice());
    }

    public function testExposesTheConfigurationToTheClient(): void
    {
        $viewModel = $this->viewModel(
            [
                'displayCartPricesBoth' => true,
                'displayCartSubtotalInclTax' => true,
                'displayCartShippingBoth' => true,
                'displayCartFullSummary' => true,
                'displayCartZeroTax' => false,
            ],
            ['type' => 1]
        );

        $this->assertSame(
            [
                'price' => 'both',
                'subtotal' => 'incl',
                'shipping' => 'both',
                'fullSummary' => true,
                'zeroTax' => false,
                'fixedTax' => 'incl_desc',
                'fixedTaxInSubtotal' => false,
            ],
            $viewModel->getConfig()
        );
    }

    public static function amounts(): array
    {
        return [
            'including' => ['incl', ['incl' => 12.0, 'excl' => null]],
            'excluding' => ['excl', ['incl' => null, 'excl' => 10.0]],
            'both' => ['both', ['incl' => 12.0, 'excl' => 10.0]],
        ];
    }

    #[DataProvider('amounts')]
    public function testPicksTheAmountsTheModeShows(string $mode, array $expected): void
    {
        $this->assertSame($expected, $this->viewModel()->pick($mode, 12.0, 10.0));
    }

    public static function subtotals(): array
    {
        return [
            'including' => [
                ['displayCartSubtotalInclTax' => true],
                [['subtotal', 'Subtotal', 12.0]],
            ],
            'excluding' => [
                [],
                [['subtotal', 'Subtotal', 10.0]],
            ],
            'both' => [
                ['displayCartSubtotalBoth' => true],
                [['subtotal_excl_tax', 'Subtotal (Excl. Tax)', 10.0], ['subtotal_incl_tax', 'Subtotal (Incl. Tax)', 12.0]],
            ],
        ];
    }

    #[DataProvider('subtotals')]
    public function testRendersTheSubtotalInTheConfiguredMode(array $config, array $expected): void
    {
        $subtotal = new DataObject(['code' => 'subtotal', 'title' => 'Subtotal', 'value' => 12.0, 'value_incl_tax' => 12.0, 'value_excl_tax' => 10.0]);
        if (!isset($config['displayCartSubtotalInclTax']) && !isset($config['displayCartSubtotalBoth'])) {
            $subtotal = new DataObject(['code' => 'subtotal', 'title' => 'Subtotal', 'value' => 10.0]);
        }

        $rows = $this->viewModel($config)->totalRows([$subtotal], '', []);

        $this->assertSame($expected, $this->summary($rows));
    }

    public static function shippings(): array
    {
        return [
            'including' => [
                ['displayCartShippingInclTax' => true],
                [['shipping', 'Shipping & Handling (Flat Rate)', 6.0]],
            ],
            'excluding' => [
                [],
                [['shipping', 'Shipping & Handling (Flat Rate)', 5.0]],
            ],
            'both' => [
                ['displayCartShippingBoth' => true],
                [
                    ['shipping_excl_tax', 'Shipping Excl. Tax (Flat Rate)', 5.0],
                    ['shipping_incl_tax', 'Shipping Incl. Tax (Flat Rate)', 6.0],
                ],
            ],
        ];
    }

    #[DataProvider('shippings')]
    public function testRendersTheShippingInTheConfiguredMode(array $config, array $expected): void
    {
        $shipping = new DataObject([
            'code' => 'shipping',
            'title' => 'Shipping & Handling (Flat Rate)',
            'value' => 5.0,
            'shipping_incl_tax' => 6.0,
        ]);

        $rows = $this->viewModel($config)->totalRows([$shipping], 'Flat Rate', []);

        $this->assertSame($expected, $this->summary($rows));
    }

    public static function taxes(): array
    {
        return [
            'zero tax hidden' => [0.0, false, false, []],
            'zero tax shown' => [0.0, true, false, [['tax', 'Tax', 0.0]]],
            'tax without the summary' => [2.0, false, false, [['tax', 'Tax', 2.0]]],
            'zero tax with the summary hidden' => [0.0, false, true, []],
            'zero tax with the summary shown' => [0.0, true, true, [['tax', 'Tax', 0.0]]],
            'tax with the full summary' => [
                2.0,
                false,
                true,
                [['tax', 'Tax', 2.0], ['tax_detail', 'State (6%)', 1.2], ['tax_detail', 'County (4%)', 0.8]],
            ],
        ];
    }

    #[DataProvider('taxes')]
    public function testRendersTheTaxRow(float $amount, bool $zeroTax, bool $fullSummary, array $expected): void
    {
        $tax = new DataObject([
            'code' => 'tax',
            'title' => 'Tax',
            'value' => $amount,
            'full_info' => $amount > 0
                ? [['amount' => 2.0, 'percent' => 10.0, 'rates' => [
                    ['title' => 'State', 'percent' => 6.0],
                    ['title' => 'County', 'percent' => 4.0],
                ]]]
                : [],
        ]);

        $rows = $this->viewModel(['displayCartFullSummary' => $fullSummary, 'displayCartZeroTax' => $zeroTax])
            ->totalRows([$tax], '', []);

        $this->assertSame($expected, $this->summary($rows));
    }

    public function testRendersTwoTaxesAppliedByTwoRules(): void
    {
        $tax = new DataObject([
            'code' => 'tax',
            'title' => 'Tax',
            'value' => 3.0,
            'full_info' => [
                ['amount' => 2.0, 'percent' => 8.0, 'rates' => [['title' => 'VAT', 'percent' => 8.0]]],
                ['amount' => 1.0, 'percent' => 4.0, 'rates' => [['title' => 'Levy', 'percent' => 4.0]]],
            ],
        ]);

        $rows = $this->viewModel(['displayCartFullSummary' => true])->totalRows([$tax], '', []);

        $this->assertSame(
            [['tax', 'Tax', 3.0], ['tax_detail', 'VAT (8%)', 2.0], ['tax_detail', 'Levy (4%)', 1.0]],
            $this->summary($rows)
        );
    }

    public static function fixedTaxRows(): array
    {
        $two = [['label' => 'Recycling', 'amount' => 2.0], ['label' => 'Battery', 'amount' => 1.0]];

        return [
            'including' => ['incl', false, $two, []],
            'including with descriptions' => ['incl_desc', false, $two, [['fixed_tax', 'Recycling', 2.0], ['fixed_tax', 'Battery', 1.0]]],
            'excluding with descriptions' => ['excl_desc_incl', false, $two, [['fixed_tax', 'Recycling', 2.0], ['fixed_tax', 'Battery', 1.0]]],
            'excluding' => ['excl', false, $two, [['fixed_tax', 'Fixed Product Tax', 3.0]]],
            'disabled' => ['off', false, $two, []],
            'already in the subtotal' => ['incl_desc', true, $two, []],
            'nothing applied' => ['excl', false, [], []],
        ];
    }

    #[DataProvider('fixedTaxRows')]
    public function testRendersTheFixedTaxRows(string $mode, bool $inSubtotal, array $fixedTaxes, array $expected): void
    {
        $typeByMode = ['incl' => 0, 'incl_desc' => 1, 'excl_desc_incl' => 2, 'excl' => 3, 'off' => 0];
        $viewModel = $this->viewModel([], ['isEnabled' => $mode !== 'off', 'type' => $typeByMode[$mode], 'inSubtotal' => $inSubtotal]);

        $rows = $viewModel->totalRows([new DataObject(['code' => 'grand_total', 'title' => 'Grand Total', 'value' => 9.0])], '', $fixedTaxes);

        $rows = array_values(array_filter($rows, static fn (array $row): bool => $row['code'] !== 'grand_total'));
        $this->assertSame($expected, $this->summary($rows));
    }

    public function testFixedTaxRowsSitBeforeTheTaxAndTheGrandTotal(): void
    {
        $totals = [
            new DataObject(['code' => 'subtotal', 'title' => 'Subtotal', 'value' => 10.0]),
            new DataObject(['code' => 'tax', 'title' => 'Tax', 'value' => 1.0, 'full_info' => []]),
            new DataObject(['code' => 'grand_total', 'title' => 'Grand Total', 'value' => 13.0]),
        ];

        $rows = $this->viewModel([], ['type' => 3])->totalRows($totals, '', [['label' => 'Recycling', 'amount' => 2.0]]);

        $this->assertSame(['subtotal', 'fixed_tax', 'tax', 'grand_total'], array_column($rows, 'code'));
    }

    public function testFixedTaxRowsStayOutWhenTheSubtotalAlreadyIncludesThem(): void
    {
        $subtotal = new DataObject(['code' => 'subtotal', 'title' => 'Subtotal', 'value' => 14.0, 'value_incl_tax' => 14.0, 'value_excl_tax' => 12.0]);
        $total = new DataObject(['code' => 'grand_total', 'title' => 'Grand Total', 'value' => 14.0]);

        $rows = $this->viewModel(['displayCartSubtotalInclTax' => true], ['type' => 1])
            ->totalRows([$subtotal, $total], '', [['label' => 'Recycling', 'amount' => 2.0]]);

        $this->assertSame(['subtotal', 'grand_total'], array_column($rows, 'code'));
    }

    public function testReplacesTheCoreFixedTaxTotalsWithItsOwnRows(): void
    {
        $totals = [
            new DataObject(['code' => 'weee', 'title' => 'FPT', 'value' => 2.0]),
            new DataObject(['code' => 'weee_tax', 'title' => 'FPT', 'value' => 2.0]),
            new DataObject(['code' => 'grand_total', 'title' => 'Grand Total', 'value' => 9.0]),
        ];

        $rows = $this->viewModel([], ['type' => 3])->totalRows($totals, '', [['label' => 'Recycling', 'amount' => 2.0]]);

        $this->assertSame(['fixed_tax', 'grand_total'], array_column($rows, 'code'));
    }

    public function testIncludingFixedTaxFootsTheSubtotal(): void
    {
        $subtotal = new DataObject(['code' => 'subtotal', 'title' => 'Subtotal', 'value' => 10.0]);

        $rows = $this->viewModel([], ['type' => 0])->totalRows([$subtotal], '', [['label' => 'Recycling', 'amount' => 2.0]]);

        $this->assertSame([['subtotal', 'Subtotal', 12.0]], $this->summary($rows));
    }

    public function testKeepsOtherTotalsAndSkipsEmptyOnes(): void
    {
        $totals = [
            new DataObject(['code' => 'discount', 'title' => 'Discount', 'value' => -3.0]),
            new DataObject(['code' => 'grand_total', 'title' => 'Grand Total', 'value' => null]),
        ];

        $this->assertSame([['discount', 'Discount', -3.0]], $this->summary($this->viewModel()->totalRows($totals, '', [])));
    }

    public function testListsTheFixedTaxesOfALine(): void
    {
        $item = $this->createMock(QuoteItem::class);
        $weee = $this->createMock(WeeeHelper::class);
        $weee->method('isEnabled')->willReturn(true);
        $weee->method('getApplied')->with($item)->willReturn([
            ['title' => 'Recycling', 'row_amount' => 2.0, 'row_amount_incl_tax' => 2.2],
            ['title' => 'Battery', 'row_amount' => 1.0, 'row_amount_incl_tax' => 1.1],
        ]);

        $viewModel = new TaxDisplay($this->createMock(TaxConfig::class), $weee);

        $this->assertSame(
            [
                ['label' => 'Recycling', 'amount' => 2.0, 'amountInclTax' => 2.2],
                ['label' => 'Battery', 'amount' => 1.0, 'amountInclTax' => 1.1],
            ],
            $viewModel->itemFixedTaxes($item)
        );
    }

    public function testSumsTheFixedTaxesOfTheCartByLabel(): void
    {
        $first = $this->createMock(QuoteItem::class);
        $second = $this->createMock(QuoteItem::class);
        $weee = $this->createMock(WeeeHelper::class);
        $weee->method('isEnabled')->willReturn(true);
        $weee->method('getApplied')->willReturnMap([
            [$first, [['title' => 'Recycling', 'row_amount' => 2.0], ['title' => 'Battery', 'row_amount' => 1.0]]],
            [$second, [['title' => 'Recycling', 'row_amount' => 3.0]]],
        ]);

        $viewModel = new TaxDisplay($this->createMock(TaxConfig::class), $weee);

        $this->assertSame(
            [['label' => 'Recycling', 'amount' => 5.0], ['label' => 'Battery', 'amount' => 1.0]],
            $viewModel->fixedTaxTotals([$first, $second])
        );
    }

    public function testAFixedTaxFreeLineListsNothing(): void
    {
        $weee = $this->createMock(WeeeHelper::class);
        $weee->method('isEnabled')->willReturn(false);

        $viewModel = new TaxDisplay($this->createMock(TaxConfig::class), $weee);

        $this->assertSame([], $viewModel->itemFixedTaxes($this->createMock(QuoteItem::class)));
    }

    private function summary(array $rows): array
    {
        return array_map(
            static fn (array $row): array => [$row['code'], (string)$row['label'], $row['amount']],
            $rows
        );
    }

    private function viewModel(array $tax = [], array $weee = []): TaxDisplay
    {
        $config = $this->createMock(TaxConfig::class);
        foreach ([
            'displayCartPricesInclTax', 'displayCartPricesBoth',
            'displayCartSubtotalInclTax', 'displayCartSubtotalBoth',
            'displayCartShippingInclTax', 'displayCartShippingBoth',
            'displayCartFullSummary', 'displayCartZeroTax',
        ] as $method) {
            $config->method($method)->willReturn($tax[$method] ?? false);
        }

        $helper = $this->createMock(WeeeHelper::class);
        $helper->method('isEnabled')->willReturn($weee['isEnabled'] ?? true);
        $helper->method('typeOfDisplay')->willReturn($weee['type'] ?? 0);
        $helper->method('includeInSubtotal')->willReturn($weee['inSubtotal'] ?? false);

        return new TaxDisplay($config, $helper);
    }
}
