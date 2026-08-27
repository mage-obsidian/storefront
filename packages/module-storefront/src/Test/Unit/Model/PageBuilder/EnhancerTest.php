<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Test\Unit\Model\PageBuilder;

use Magento\Framework\View\Helper\SecureHtmlRenderer;
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;
use MageObsidian\Storefront\Model\PageBuilder\Enhancer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class EnhancerTest extends TestCase
{
    public function testPullsTheBehaviourInBesideATabSet(): void
    {
        $enhanced = $this->enhancer()->enhance('<div data-content-type="tabs"></div>');

        $this->assertStringContainsString('src="/js/page-builder.js"', $enhanced);
        $this->assertStringContainsString('data-content-type="tabs"', $enhanced);
    }

    public function testPullsItInBesideASlider(): void
    {
        $this->assertStringContainsString(
            '<script',
            $this->enhancer()->enhance('<div data-content-type="slider"></div>')
        );
    }

    /**
     * Everything else Page Builder renders is finished markup, so a store whose
     * authors never used a tab set never pays for one.
     */
    public function testLeavesContentThatNeedsNoBehaviourUntouched(): void
    {
        $plain = '<div data-content-type="row"><div data-content-type="text"><p>Words</p></div></div>';

        $this->assertSame($plain, $this->enhancer()->enhance($plain));
    }

    public function testLeavesContentThatIsNotPageBuilderAtAllUntouched(): void
    {
        $this->assertSame('<p>Just words</p>', $this->enhancer()->enhance('<p>Just words</p>'));
    }

    // An unbuilt theme is a broken deployment, not a reason to lose the page.
    public function testKeepsTheContentWhenTheBuildHasNotRun(): void
    {
        $resolver = $this->createMock(ViteResolver::class);
        $resolver->method('getViteFileUrl')->willThrowException(new RuntimeException('no manifest'));

        $content = '<div data-content-type="tabs"></div>';

        $this->assertSame($content, $this->enhancer($resolver)->enhance($content));
    }

    private function enhancer(?ViteResolver $resolver = null): Enhancer
    {
        if ($resolver === null) {
            $resolver = $this->createMock(ViteResolver::class);
            $resolver->method('getViteFileUrl')->willReturn('/js/page-builder.js');
        }

        $renderer = $this->createMock(SecureHtmlRenderer::class);
        $renderer->method('renderTag')->willReturnCallback(
            static fn (string $tag, array $attributes): string
                => '<script type="module" src="' . $attributes['src'] . '"></script>'
        );

        return new Enhancer($resolver, $renderer);
    }
}
