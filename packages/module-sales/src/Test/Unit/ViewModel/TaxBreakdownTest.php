<?php
declare(strict_types=1);

namespace MageObsidian\Sales\Test\Unit\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Tax\Helper\Data as TaxHelper;
use MageObsidian\Sales\ViewModel\TaxBreakdown;
use PHPUnit\Framework\TestCase;

/**
 * Per-rate tax detail for the order/invoice/credit-memo totals. We assert the
 * helper rows are normalised, that the full-summary flag mirrors config, and that
 * a helper failure degrades to no rows (the single Tax line still renders).
 */
class TaxBreakdownTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(TaxHelper::class)) {
            $this->markTestSkipped('Magento_Tax is not available in this runtime.');
        }
    }

    public function testNormalisesCalculatedTaxRows(): void
    {
        $taxHelper = $this->createMock(TaxHelper::class);
        $taxHelper->method('getCalculatedTaxes')->willReturn([
            ['title' => 'US-MI-*-Rate 1', 'percent' => 8.25, 'tax_amount' => 4.87, 'base_tax_amount' => 4.87],
        ]);

        $viewModel = new TaxBreakdown($taxHelper, $this->createMock(ScopeConfigInterface::class));

        $this->assertSame(
            [['title' => 'US-MI-*-Rate 1', 'percent' => 8.25, 'amount' => 4.87]],
            $viewModel->getRates(new DataObject())
        );
    }

    public function testReturnsNoRowsWhenDocumentHasNoTax(): void
    {
        $taxHelper = $this->createMock(TaxHelper::class);
        $taxHelper->method('getCalculatedTaxes')->willReturn([]);

        $viewModel = new TaxBreakdown($taxHelper, $this->createMock(ScopeConfigInterface::class));

        $this->assertSame([], $viewModel->getRates(new DataObject()));
    }

    public function testDegradesToNoRowsWhenHelperThrows(): void
    {
        $taxHelper = $this->createMock(TaxHelper::class);
        $taxHelper->method('getCalculatedTaxes')->willThrowException(new \RuntimeException('boom'));

        $viewModel = new TaxBreakdown($taxHelper, $this->createMock(ScopeConfigInterface::class));

        $this->assertSame([], $viewModel->getRates(new DataObject()));
    }

    public function testFullSummaryMirrorsStoreConfig(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->with('tax/sales_display/full_summary', 'store')->willReturn(true);

        $viewModel = new TaxBreakdown($this->createMock(TaxHelper::class), $scopeConfig);

        $this->assertTrue($viewModel->isFullSummary());
    }
}
