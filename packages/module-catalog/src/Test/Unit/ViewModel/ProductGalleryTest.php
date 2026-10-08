<?php
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Catalog\Test\Unit\ViewModel;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Csp\Model\Collector\DynamicCollector;
use Magento\Csp\Model\Policy\FetchPolicy;
use Magento\Framework\DataObject;
use Magento\Framework\Registry;
use MageObsidian\Catalog\Service\Product\VideoEmbed;
use MageObsidian\Catalog\ViewModel\ProductGallery;
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

    private function viewModel(?Product $product, ?ImageHelper $imageHelper = null, ?DynamicCollector $policies = null): ProductGallery
    {
        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->with('current_product')->willReturn($product);

        return new ProductGallery(
            $registry,
            $imageHelper ?? $this->createMock(ImageHelper::class),
            new VideoEmbed(),
            $policies ?? $this->createMock(DynamicCollector::class)
        );
    }

    private function galleryProduct(): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getName')->willReturn('Tee');
        $product->method('getData')->with('image')->willReturn('/a.jpg');
        $product->method('getMediaGalleryImages')->willReturn([
            new DataObject(['file' => '/a.jpg', 'media_type' => 'image', 'label' => 'Front', 'url' => '/full/a.jpg', 'disabled' => 0]),
            new DataObject(['file' => '/b.jpg', 'media_type' => 'image', 'label' => 'Back', 'url' => '/full/b.jpg', 'disabled' => 0]),
        ]);

        return $product;
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

    public function testMainImageIsTheTileTheGalleryRendersFirst(): void
    {
        $viewModel = $this->viewModel($this->galleryProduct(), $this->imageHelper('https://img/x.jpg'));

        $this->assertSame($viewModel->getImages()[0], $viewModel->getMainImage());
        $this->assertSame('Front', $viewModel->getMainImage()['label']);
    }

    public function testMainImageIsEmptyWithoutAProduct(): void
    {
        $this->assertSame([], $this->viewModel(null)->getMainImage());
    }

    public function testSizesIsTheOneHintBothTheImgAndItsPreloadCarry(): void
    {
        $this->assertSame(
            ProductGallery::LARGE_SIZES,
            $this->viewModel(null)->getLargeSizes()
        );
    }

    public function testTilesAreBuiltOnceSoTheHeadPreloadCostsNothing(): void
    {
        $registry = $this->createMock(Registry::class);
        $registry->expects($this->once())->method('registry')->willReturn($this->galleryProduct());
        $viewModel = new ProductGallery(
            $registry,
            $this->imageHelper('https://img/x.jpg'),
            new VideoEmbed(),
            $this->createMock(DynamicCollector::class)
        );

        $first = $viewModel->getImages();

        $this->assertSame($first, $viewModel->getImages());
        $this->assertSame($first[0], $viewModel->getMainImage());
    }

    private function videoProduct(array $video): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getName')->willReturn('Tee');
        $product->method('getData')->with('image')->willReturn('/a.jpg');
        $product->method('getMediaGalleryImages')->willReturn([
            new DataObject(['file' => '/a.jpg', 'media_type' => 'image', 'label' => 'Front', 'url' => '/full/a.jpg', 'disabled' => 0]),
            new DataObject($video + ['file' => '/v.jpg', 'media_type' => 'external-video', 'label' => 'Clip', 'url' => '/full/v.jpg', 'disabled' => 0]),
        ]);

        return $product;
    }

    public function testAProviderVideoBecomesATileCarryingItsPreviewAndItsPlayer(): void
    {
        $product = $this->videoProduct([
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'video_title' => 'Unboxing',
        ]);

        $tiles = $this->viewModel($product, $this->imageHelper('https://img/x.jpg'))->getImages();

        $this->assertCount(2, $tiles);
        $this->assertNull($tiles[0]['video']);
        $this->assertSame('https://img/x.jpg', $tiles[1]['large']);
        $this->assertSame('https://img/x.jpg', $tiles[1]['thumb']);
        $this->assertSame('Clip', $tiles[1]['label']);
        $this->assertFalse($tiles[1]['isMain']);
        $this->assertSame([
            'provider' => 'youtube',
            'embed' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0',
            'watch' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'title' => 'Unboxing',
        ], $tiles[1]['video']);
    }

    public function testAVideoTakesTheProductNameWhenItHasNeitherLabelNorTitle(): void
    {
        $product = $this->videoProduct(['video_url' => 'https://vimeo.com/76979871', 'label' => null, 'video_title' => null]);

        $tiles = $this->viewModel($product, $this->imageHelper('https://img/x.jpg'))->getImages();

        $this->assertSame('Tee', $tiles[1]['label']);
        $this->assertSame('Tee', $tiles[1]['video']['title']);
    }

    public function testAVideoFromAProviderWeDoNotEmbedStaysOut(): void
    {
        $product = $this->videoProduct(['video_url' => 'https://www.dailymotion.com/video/x7tgad0']);

        $this->assertCount(1, $this->viewModel($product, $this->imageHelper('https://img/x.jpg'))->getImages());
    }

    public function testADisabledVideoStaysOut(): void
    {
        $product = $this->videoProduct(['video_url' => 'https://vimeo.com/76979871', 'disabled' => 1]);

        $this->assertCount(1, $this->viewModel($product, $this->imageHelper('https://img/x.jpg'))->getImages());
    }

    public function testAPageWithAVideoLetsFramesLoadFromTheTwoPlayersAndOnlyThose(): void
    {
        $policies = $this->createMock(DynamicCollector::class);
        $policies->expects($this->once())->method('add')->with($this->callback(
            static fn (FetchPolicy $policy): bool => $policy->getId() === 'frame-src'
                && $policy->getHostSources() === ['www.youtube-nocookie.com', 'player.vimeo.com']
                && !$policy->isNoneAllowed()
                && !$policy->isInlineAllowed()
                && !$policy->isEvalAllowed()
        ));
        $product = $this->videoProduct(['video_url' => 'https://vimeo.com/76979871']);

        $this->viewModel($product, $this->imageHelper('https://img/x.jpg'), $policies)->getImages();
    }

    public function testAPageWithoutAVideoLetsNoFrameLoad(): void
    {
        $policies = $this->createMock(DynamicCollector::class);
        $policies->expects($this->never())->method('add');

        $this->viewModel($this->galleryProduct(), $this->imageHelper('https://img/x.jpg'), $policies)->getImages();
    }

    public function testAVideoTheStoreCannotEmbedLetsNoFrameLoad(): void
    {
        $policies = $this->createMock(DynamicCollector::class);
        $policies->expects($this->never())->method('add');
        $product = $this->videoProduct(['video_url' => 'https://www.dailymotion.com/video/x7tgad0']);

        $this->viewModel($product, $this->imageHelper('https://img/x.jpg'), $policies)->getImages();
    }

    public function testTheFramesAreAllowedOncePerPage(): void
    {
        $policies = $this->createMock(DynamicCollector::class);
        $policies->expects($this->once())->method('add');
        $viewModel = $this->viewModel(
            $this->videoProduct(['video_url' => 'https://vimeo.com/76979871']),
            $this->imageHelper('https://img/x.jpg'),
            $policies
        );

        $viewModel->getImages();
        $viewModel->getImages();
    }
}
