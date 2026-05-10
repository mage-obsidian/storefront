<?php
declare(strict_types=1);

namespace MageObsidian\Sales\Test\Unit\ViewModel;

use Magento\Framework\DataObject;
use Magento\Framework\Serialize\SerializerInterface;
use MageObsidian\Sales\ViewModel\ItemOptions;
use PHPUnit\Framework\TestCase;

/**
 * Restores the per-item option lines (custom options, configurable attributes,
 * additional options) and bundle selection children the legacy sales renderers
 * showed. We assert the merge order, value flattening and child decoding.
 */
class ItemOptionsTest extends TestCase
{
    /**
     * @var ItemOptions
     */
    private ItemOptions $viewModel;

    protected function setUp(): void
    {
        if (!class_exists(DataObject::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('unserialize')->willReturnCallback(
            static fn (string $value): array => json_decode($value, true) ?? []
        );

        $this->viewModel = new ItemOptions($serializer);
    }

    public function testMergesCustomConfigurableAndAdditionalOptions(): void
    {
        $item = new DataObject([
            'product_options' => [
                'options' => [['label' => 'Engraving', 'value' => 'ACME']],
                'attributes_info' => [['label' => 'Size', 'value' => '29']],
                'additional_options' => [['label' => 'Note', 'value' => 'Fragile']],
            ],
        ]);

        $this->assertSame(
            [
                ['label' => 'Engraving', 'value' => 'ACME'],
                ['label' => 'Note', 'value' => 'Fragile'],
                ['label' => 'Size', 'value' => '29'],
            ],
            $this->viewModel->getOptions($item)
        );
    }

    public function testFlattensListAndLabelledValuesAndStripsTags(): void
    {
        $item = new DataObject([
            'product_options' => [
                'options' => [
                    ['label' => 'Extras', 'value' => ['Pin', 'Patch']],
                    ['label' => 'Wrapping', 'value' => ['value' => 'Linen <b>++</b>']],
                ],
            ],
        ]);

        $this->assertSame(
            [
                ['label' => 'Extras', 'value' => 'Pin, Patch'],
                ['label' => 'Wrapping', 'value' => 'Linen ++'],
            ],
            $this->viewModel->getOptions($item)
        );
    }

    public function testReturnsNoOptionsWhenProductOptionsAbsent(): void
    {
        $this->assertSame([], $this->viewModel->getOptions(new DataObject()));
    }

    public function testResolvesWrappedOrderItemForDocumentItems(): void
    {
        $orderItem = new DataObject([
            'product_options' => ['attributes_info' => [['label' => 'Color', 'value' => 'White']]],
        ]);
        $invoiceItem = new class(['order_item' => $orderItem]) extends DataObject {
            public function getOrderItem(): DataObject
            {
                return $this->getData('order_item');
            }
        };

        $this->assertSame(
            [['label' => 'Color', 'value' => 'White']],
            $this->viewModel->getOptions($invoiceItem)
        );
    }

    public function testReturnsBundleChildrenWithSelectionQtyAndPrice(): void
    {
        $child = new DataObject([
            'name' => 'Sprite Stasis Ball',
            'qty_ordered' => 1.0,
            'price' => 0.0,
            'product_options' => [
                'bundle_selection_attributes' => json_encode(['qty' => 2, 'price' => 27.0]),
            ],
        ]);
        $bundle = new DataObject(['has_children' => true, 'children_items' => [$child]]);

        $this->assertSame(
            [['name' => 'Sprite Stasis Ball', 'qty' => 2.0, 'price' => 27.0]],
            $this->viewModel->getChildren($bundle)
        );
    }

    public function testReturnsNoChildrenForSimpleItems(): void
    {
        $this->assertSame([], $this->viewModel->getChildren(new DataObject(['has_children' => false])));
    }

    public function testSkipsConfigurableChildrenLackingSelectionAttributes(): void
    {
        $simpleChild = new DataObject(['name' => 'Variant', 'product_options' => []]);
        $configurable = new DataObject(['has_children' => true, 'children_items' => [$simpleChild]]);

        $this->assertSame([], $this->viewModel->getChildren($configurable));
    }
}
