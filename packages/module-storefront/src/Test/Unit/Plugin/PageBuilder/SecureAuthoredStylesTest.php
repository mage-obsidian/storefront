<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\Plugin\PageBuilder;

use Magento\Framework\Filter\Template;
use MageObsidian\ModernFrontend\Service\Theme\ObsidianThemeDetector;
use MageObsidian\Storefront\Model\PageBuilder\Enhancer;
use MageObsidian\Storefront\Model\PageBuilder\ImageDimensions;
use MageObsidian\Storefront\Model\PageBuilder\MapProvider;
use MageObsidian\Storefront\Model\PageBuilder\StyleBlock;
use MageObsidian\Storefront\Plugin\PageBuilder\SecureAuthoredStyles;
use PHPUnit\Framework\TestCase;

class SecureAuthoredStylesTest extends TestCase
{
    private array $calls = [];

    protected function setUp(): void
    {
        if (!class_exists(Template::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
        $this->calls = [];
    }

    public function testOutsideAnObsidianThemeTheFilteredContentIsReturnedUntouched(): void
    {
        $html = '<style>#html-body [data-pb-style=A]{padding:1px}</style><div data-content-type="tabs"></div>';

        $result = $this->plugin(false)->afterFilter($this->createStub(Template::class), $html);

        $this->assertSame($html, $result);
        $this->assertSame([], $this->calls);
    }

    public function testUnderAnObsidianThemeTheContentPassesThroughEveryStepInOrder(): void
    {
        $result = $this->plugin(true)->afterFilter($this->createStub(Template::class), 'html');

        $this->assertSame('html|style|image|map|enhanced', $result);
        $this->assertSame(['style', 'image', 'map', 'enhanced'], $this->calls);
    }

    private function plugin(bool $obsidianTheme): SecureAuthoredStyles
    {
        $detector = $this->createStub(ObsidianThemeDetector::class);
        $detector->method('isActive')->willReturn($obsidianTheme);

        $styleBlock = $this->createStub(StyleBlock::class);
        $styleBlock->method('rewrite')->willReturnCallback(fn (string $html): string => $this->step($html, 'style'));
        $imageDimensions = $this->createStub(ImageDimensions::class);
        $imageDimensions->method('inject')
            ->willReturnCallback(fn (string $html): string => $this->step($html, 'image'));
        $mapProvider = $this->createStub(MapProvider::class);
        $mapProvider->method('inject')->willReturnCallback(fn (string $html): string => $this->step($html, 'map'));
        $enhancer = $this->createStub(Enhancer::class);
        $enhancer->method('enhance')->willReturnCallback(fn (string $html): string => $this->step($html, 'enhanced'));

        return new SecureAuthoredStyles($styleBlock, $enhancer, $imageDimensions, $mapProvider, $detector);
    }

    private function step(string $html, string $name): string
    {
        $this->calls[] = $name;

        return $html . '|' . $name;
    }
}
