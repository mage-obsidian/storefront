<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\Block;

use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use MageObsidian\ModernFrontend\Service\RuntimeScriptReader;
use MageObsidian\Storefront\Block\NavigationRetention;
use MageObsidian\Storefront\ViewModel\NavigationContinuity;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class NavigationRetentionTest extends TestCase
{
    private const string VIEW_DIR = __DIR__ . '/../../../view';

    protected function setUp(): void
    {
        if (!class_exists(Context::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    public function testEmitsTheRetentionScriptWithTheMarkerItWaitsFor(): void
    {
        $renderer = $this->createMock(SecureHtmlRenderer::class);
        $renderer->expects($this->once())
            ->method('renderTag')
            ->with('script', ['data-marker' => 'obsidian-main-end'], $this->callback(
                static fn(string $script): bool => str_starts_with($script, '(function (doc, nav) {')
                    && !str_contains($script, 'SPDX')
                    && !str_contains($script, 'This file is part of')
            ), false)
            ->willReturn('<script data-marker="obsidian-main-end" nonce="abc">…</script>');

        $html = $this->render($renderer, enabled: true, viewDir: self::VIEW_DIR);

        $this->assertSame('<script data-marker="obsidian-main-end" nonce="abc">…</script>', $html);
    }

    public function testEmitsNothingWhenRetentionIsOff(): void
    {
        $renderer = $this->createMock(SecureHtmlRenderer::class);
        $renderer->expects($this->never())->method('renderTag');

        $this->assertSame('', $this->render($renderer, enabled: false, viewDir: self::VIEW_DIR));
    }

    public function testEmitsNothingWhenTheScriptIsMissing(): void
    {
        $renderer = $this->createMock(SecureHtmlRenderer::class);
        $renderer->expects($this->never())->method('renderTag');

        $this->assertSame('', $this->render($renderer, enabled: true, viewDir: sys_get_temp_dir() . '/absent'));
    }

    private function render(SecureHtmlRenderer $renderer, bool $enabled, string $viewDir): string
    {
        $continuity = $this->createStub(NavigationContinuity::class);
        $continuity->method('isEnabled')->willReturn($enabled);
        $continuity->method('getMarkerId')->willReturn('obsidian-main-end');

        $reader = $this->createStub(Reader::class);
        $reader->method('getModuleDir')->willReturn($viewDir);

        $block = new NavigationRetention(
            $this->createStub(Context::class),
            $continuity,
            $renderer,
            new RuntimeScriptReader($reader)
        );

        return (string)(new ReflectionMethod($block, '_toHtml'))->invoke($block);
    }
}
