<?php
declare(strict_types=1);

namespace MageObsidian\Catalog\Test\Unit\ViewModel;

use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Swatches\Block\LayeredNavigation\RenderLayered;
use Magento\Swatches\Helper\Data as SwatchHelper;
use MageObsidian\Catalog\ViewModel\LayeredSwatch;
use PHPUnit\Framework\TestCase;

/**
 * Swatch support for the layered-navigation sidebar: detects swatch attributes
 * and exposes the native render data, degrading to empty when a filter has no
 * attribute or the renderer throws. Needs Magento Swatches/Catalog types, so it
 * runs in a Magento root.
 */
class LayeredSwatchTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(AbstractFilter::class) && !class_exists(AbstractFilter::class)) {
            $this->markTestSkipped('Magento Catalog is not available in this runtime.');
        }
        if (!class_exists(RenderLayered::class)) {
            $this->markTestSkipped('Magento Swatches is not available in this runtime.');
        }
    }

    private function filter(?Attribute $attribute): AbstractFilter
    {
        $filter = $this->createMock(AbstractFilter::class);
        $filter->method('getAttributeModel')->willReturn($attribute);

        return $filter;
    }

    public function testIsSwatchWhenHelperConfirmsTheAttribute(): void
    {
        $attribute = $this->createMock(Attribute::class);
        $helper = $this->createMock(SwatchHelper::class);
        $helper->method('isSwatchAttribute')->with($attribute)->willReturn(true);

        $vm = new LayeredSwatch($helper, $this->createMock(RenderLayered::class));

        $this->assertTrue($vm->isSwatch($this->filter($attribute)));
    }

    public function testIsNotSwatchForANonSwatchAttribute(): void
    {
        $attribute = $this->createMock(Attribute::class);
        $helper = $this->createMock(SwatchHelper::class);
        $helper->method('isSwatchAttribute')->willReturn(false);

        $vm = new LayeredSwatch($helper, $this->createMock(RenderLayered::class));

        $this->assertFalse($vm->isSwatch($this->filter($attribute)));
    }

    public function testIsNotSwatchWhenTheAttributeLookupThrows(): void
    {
        $helper = $this->createMock(SwatchHelper::class);
        $helper->method('isSwatchAttribute')->willThrowException(new \RuntimeException('no attribute'));

        $vm = new LayeredSwatch($helper, $this->createMock(RenderLayered::class));

        $this->assertFalse($vm->isSwatch($this->filter($this->createMock(Attribute::class))));
    }

    public function testGetDataNormalizesColorAndTextSwatches(): void
    {
        $filter = $this->filter($this->createMock(Attribute::class));
        $renderer = $this->createMock(RenderLayered::class);
        $renderer->method('setSwatchFilter')->with($filter)->willReturnSelf();
        $renderer->method('getSwatchData')->willReturn([
            'options' => [
                '10' => ['label' => 'Black', 'link' => '/c?color=10'],
                '11' => ['label' => 'Large', 'link' => '/c?size=11'],
            ],
            'swatches' => [
                '10' => ['type' => 1, 'value' => '#000000'],
                '11' => ['type' => 0, 'value' => 'L'],
            ],
        ]);

        $vm = new LayeredSwatch($this->createMock(SwatchHelper::class), $renderer);
        $items = $vm->getData($filter);

        $this->assertSame('Black', $items[0]['label']);
        $this->assertSame('#000000', $items[0]['color']);
        $this->assertSame('Large', $items[1]['label']);
        $this->assertNull($items[1]['color']);
    }

    public function testGetDataRejectsAnInvalidColorValue(): void
    {
        $filter = $this->filter($this->createMock(Attribute::class));
        $renderer = $this->createMock(RenderLayered::class);
        $renderer->method('setSwatchFilter')->willReturnSelf();
        $renderer->method('getSwatchData')->willReturn([
            'options' => ['10' => ['label' => 'X', 'link' => '/c']],
            'swatches' => ['10' => ['type' => 1, 'value' => 'red; background:url(x)']],
        ]);

        $vm = new LayeredSwatch($this->createMock(SwatchHelper::class), $renderer);

        $this->assertNull($vm->getData($filter)[0]['color']);
    }

    public function testGetDataIsEmptyWhenTheRendererThrows(): void
    {
        $renderer = $this->createMock(RenderLayered::class);
        $renderer->method('setSwatchFilter')->willThrowException(new \RuntimeException('boom'));

        $vm = new LayeredSwatch($this->createMock(SwatchHelper::class), $renderer);

        $this->assertSame([], $vm->getData($this->filter(null)));
    }
}
