<?php
declare(strict_types=1);

namespace MageObsidian\Catalog\Test\Unit\ViewModel;

use Magento\Catalog\Helper\Output as OutputHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\Escaper;
use MageObsidian\Catalog\ViewModel\ProductAttributeOutput;
use PHPUnit\Framework\TestCase;

/**
 * Mirrors the two branches core's compare template takes: a string value is
 * printed as the output helper returns it, anything else is escaped on top. Needs
 * Magento Catalog types, so it runs in a Magento root (see phpunit.ci.xml).
 */
class ProductAttributeOutputTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Product::class)) {
            $this->markTestSkipped('Magento Catalog is not available in this runtime.');
        }
    }

    private function subject(OutputHelper $output): ProductAttributeOutput
    {
        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtml')->willReturnCallback(
            static fn (mixed $value): string => 'escaped(' . $value . ')'
        );

        return new ProductAttributeOutput($output, $escaper);
    }

    public function testAStringValueComesBackAsTheFilterReturnedIt(): void
    {
        $product = $this->createMock(Product::class);

        $output = $this->createMock(OutputHelper::class);
        $output->expects($this->once())
            ->method('productAttribute')
            ->with($product, 'Cotton &amp; linen', 'material')
            ->willReturn('Cotton &amp; linen');

        $this->assertSame('Cotton &amp; linen', $this->subject($output)->filter($product, 'Cotton &amp; linen', 'material'));
    }

    public function testANonStringValueIsEscapedOnTopOfTheFilter(): void
    {
        $product = $this->createMock(Product::class);

        $output = $this->createMock(OutputHelper::class);
        $output->method('productAttribute')->willReturn('42');

        $this->assertSame('escaped(42)', $this->subject($output)->filter($product, 42, 'weight'));
    }
}
