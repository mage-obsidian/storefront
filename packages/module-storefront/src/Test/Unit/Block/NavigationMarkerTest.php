<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\Block;

use Magento\Framework\View\Element\Context;
use MageObsidian\Storefront\Block\NavigationMarker;
use MageObsidian\Storefront\ViewModel\NavigationContinuity;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class NavigationMarkerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Context::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    public function testEmitsAnEmptyHiddenElementCarryingTheMarkerId(): void
    {
        $this->assertSame(
            '<div id="obsidian-main-end" hidden></div>',
            $this->render(enabled: true, markerId: 'obsidian-main-end')
        );
    }

    public function testEscapesTheMarkerId(): void
    {
        $this->assertSame(
            '<div id="a&quot;b" hidden></div>',
            $this->render(enabled: true, markerId: 'a"b')
        );
    }

    public function testEmitsNothingWhenRetentionIsOff(): void
    {
        $this->assertSame('', $this->render(enabled: false, markerId: 'obsidian-main-end'));
    }

    private function render(bool $enabled, string $markerId): string
    {
        $continuity = $this->createStub(NavigationContinuity::class);
        $continuity->method('isEnabled')->willReturn($enabled);
        $continuity->method('getMarkerId')->willReturn($markerId);

        $block = new NavigationMarker($this->createStub(Context::class), $continuity);

        return (string)(new ReflectionMethod($block, '_toHtml'))->invoke($block);
    }
}
