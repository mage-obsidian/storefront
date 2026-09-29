<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\Block;

use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;
use MageObsidian\Storefront\Block\NavigationProgress;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class NavigationProgressTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Context::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    public function testEmitsTheNavigationProgressScript(): void
    {
        $resolver = $this->createMock(ViteResolver::class);
        $resolver->expects($this->once())
            ->method('getViteFileUrl')
            ->with('MageObsidian_Storefront::js/navigationProgress')
            ->willReturn('/static/generated/MageObsidian_Storefront/js/navigationProgress.js');

        $renderer = $this->createMock(SecureHtmlRenderer::class);
        $renderer->expects($this->once())
            ->method('renderTag')
            ->with(
                'script',
                [
                    'type' => 'module',
                    'src' => '/static/generated/MageObsidian_Storefront/js/navigationProgress.js',
                    'fetchpriority' => 'low',
                ],
                '',
                false
            )
            ->willReturn('<script type="module" src="/x.js"></script>');

        $block = new NavigationProgress($this->createStub(Context::class), $resolver, $renderer);

        $this->assertSame(
            '<script type="module" src="/x.js"></script>',
            (string)(new ReflectionMethod($block, '_toHtml'))->invoke($block)
        );
    }
}
