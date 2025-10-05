<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\ViewModel;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\Framework\Registry;
use MageObsidian\Storefront\ViewModel\ProductGallery;
use PHPUnit\Framework\TestCase;

/**
 * PDP gallery ViewModel. We assert the contract the template relies on: visible
 * media images become {large,thumb,full,label,isMain} tiles, disabled and
 * non-image entries are skipped, the base image (or placeholder) is the fallback
 * when there is no gallery, and a missing product degrades to an empty list.
 * Needs Magento Catalog types, so it runs in a Magento root (see phpunit.ci.xml).
 */
class ProductGalleryTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Product::class)) {
            $this->markTestSkipped('Magento Catalog is not available in this runtime.');
        }
    }

    private function viewModel(?Product $product, ?ImageHelper $imageHelper = null): ProductGallery
    {
        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->with('current_product')->willReturn($product);

        return new ProductGallery($registry, $imageHelper ?? $this->createMock(ImageHelper::class));
    }

    private function imageHelper(string $url): ImageHelper
    {
        $helper = $this->createMock(ImageHelper::class);
        $helper->method('init')->willReturnSelf();
        $helper->method('setImageFile')->willReturnSelf();
        $helper->method('getUrl')->willReturn($url);

        return $helper;
    }

    public function testNoProductYieldsNoImages(): void
    {
        $this->assertSame([], $this->viewModel(null)->getImages());
    }

    public function testBuildsTilesFromVisibleMediaImages(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getName')->willReturn('Tee');
        $product->method('getData')->with('image')->willReturn('/a.jpg');
        $product->method('getMediaGalleryImages')->willReturn([
            new DataObject(['file' => '/a.jpg', 'media_type' => 'image', 'label' => 'Front', 'url' => '/full/a.jpg', 'disabled' => 0]),
            new DataObject(['file' => '/b.jpg', 'media_type' => 'image', 'label' => 'Back', 'url' => '/full/b.jpg', 'disabled' => 1]),
            new DataObject(['file' => '/c.mp4', 'media_type' => 'external-video', 'label' => 'Clip', 'url' => '/full/c.mp4', 'disabled' => 0]),
        ]);

        $tiles = $this->viewModel($product, $this->imageHelper('https://img/x.jpg'))->getImages();

        $this->assertCount(1, $tiles);
        $this->assertSame('Front', $tiles[0]['label']);
        $this->assertTrue($tiles[0]['isMain']);
        $this->assertSame('/full/a.jpg', $tiles[0]['full']);
        $this->assertSame('https://img/x.jpg', $tiles[0]['large']);
    }

    public function testFallsBackToBaseImageWhenNoGallery(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getName')->willReturn('Tee');
        $product->method('getData')->with('image')->willReturn('/a.jpg');
        $product->method('getMediaGalleryImages')->willReturn(null);

        $tiles = $this->viewModel($product, $this->imageHelper('https://img/base.jpg'))->getImages();

        $this->assertCount(1, $tiles);
        $this->assertTrue($tiles[0]['isMain']);
        $this->assertSame('https://img/base.jpg', $tiles[0]['large']);
        $this->assertSame('https://img/base.jpg', $tiles[0]['full']);
    }
}
