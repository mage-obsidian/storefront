<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Test\Unit\Model\PageBuilder;

use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use MageObsidian\Storefront\Model\PageBuilder\ImageDimensions;
use PHPUnit\Framework\TestCase;

class ImageDimensionsTest extends TestCase
{
    private string $file = '';

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'obsidian') . '.png';
        $image = imagecreatetruecolor(320, 180);
        imagepng($image, $this->file);
    }

    protected function tearDown(): void
    {
        if ($this->file !== '' && is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testGivesAPageBuilderImageItsOwnDimensions(): void
    {
        $html = '<img class="pagebuilder-mobile-hidden" src="https://shop.test/media/wysiwyg/hero.png" '
            . 'alt="" data-element="desktop_image">';

        $injected = $this->dimensions()->inject($html);

        $this->assertStringContainsString('width="320"', $injected);
        $this->assertStringContainsString('height="180"', $injected);
    }

    public function testLeavesAnImageThatAlreadyDeclaresThemAlone(): void
    {
        $html = '<img src="https://shop.test/media/wysiwyg/hero.png" width="10" height="10" data-element="desktop_image">';

        $this->assertSame($html, $this->dimensions()->inject($html));
    }

    public function testLeavesAnImageThatIsNotPageBuilderAlone(): void
    {
        $html = '<img src="https://shop.test/media/wysiwyg/hero.png" alt="">';

        $this->assertSame($html, $this->dimensions()->inject($html));
    }

    public function testLeavesTheOutputAloneWhenTheFileIsNotThere(): void
    {
        $html = '<img src="https://shop.test/media/wysiwyg/gone.png" data-element="desktop_image">';

        $this->assertSame($html, $this->dimensions(false)->inject($html));
    }

    public function testLeavesASourceOutsideTheMediaDirectoryAlone(): void
    {
        $html = '<img src="https://elsewhere.test/assets/hero.png" data-element="desktop_image">';

        $this->assertSame($html, $this->dimensions()->inject($html));
    }

    public function testRefusesASourceThatClimbsOutOfTheMediaDirectory(): void
    {
        $html = '<img src="https://shop.test/media/../../app/etc/env.php" data-element="desktop_image">';

        $this->assertSame($html, $this->dimensions()->inject($html));
    }

    public function testReadsEachFileOnceHoweverOftenItAppears(): void
    {
        $one = '<img src="https://shop.test/media/wysiwyg/hero.png" data-element="desktop_image">';
        $media = $this->createMock(ReadInterface::class);
        $media->method('isExist')->willReturn(true);
        $media->expects($this->once())->method('getAbsolutePath')->willReturn($this->file);

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryRead')->willReturn($media);

        $injected = (new ImageDimensions($filesystem))->inject($one . $one . $one);

        $this->assertSame(3, substr_count($injected, 'width="320"'));
    }

    public function testLeavesContentWithNoImagesAlone(): void
    {
        $html = '<div data-content-type="text"><p>Words</p></div>';

        $this->assertSame($html, $this->dimensions()->inject($html));
    }

    private function dimensions(bool $exists = true): ImageDimensions
    {
        $media = $this->createMock(ReadInterface::class);
        $media->method('isExist')->willReturn($exists);
        $media->method('getAbsolutePath')->willReturn($this->file);

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryRead')->willReturn($media);

        return new ImageDimensions($filesystem);
    }
}
