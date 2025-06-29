<?php
declare(strict_types=1);

namespace MageObsidian\Wishlist\Test\Unit\ViewModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use MageObsidian\Wishlist\ViewModel\ProductThumbnail;
use PHPUnit\Framework\TestCase;

/**
 * The wish-list product thumbnail. We assert it returns the resized URL with
 * intrinsic dimensions and falls back to the product name for the label.
 */
class ProductThumbnailTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(ProductInterface::class) || !class_exists(ImageHelper::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    public function testReturnsResizedImageWithDimensions(): void
    {
        $product = $this->createMock(ProductInterface::class);

        $helper = $this->createMock(ImageHelper::class);
        $helper->method('init')->willReturnSelf();
        $helper->method('getUrl')->willReturn('https://shop.test/media/catalog/p.jpg');
        $helper->method('getWidth')->willReturn(480);
        $helper->method('getHeight')->willReturn(600);
        $helper->method('getLabel')->willReturn('Blue Tee');

        $result = (new ProductThumbnail($helper))->get($product);

        $this->assertSame([
            'url' => 'https://shop.test/media/catalog/p.jpg',
            'width' => 480,
            'height' => 600,
            'label' => 'Blue Tee',
        ], $result);
    }

    public function testFallsBackToProductNameForLabel(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getName')->willReturn('Joust Bag');

        $helper = $this->createMock(ImageHelper::class);
        $helper->method('init')->willReturnSelf();
        $helper->method('getUrl')->willReturn('u');
        $helper->method('getWidth')->willReturn(480);
        $helper->method('getHeight')->willReturn(600);
        $helper->method('getLabel')->willReturn(null);

        $result = (new ProductThumbnail($helper))->get($product);

        $this->assertSame('Joust Bag', $result['label']);
    }
}
