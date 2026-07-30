<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Test\Unit\Block;

use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;
use MageObsidian\Storefront\Block\FormKeyProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Needs Magento's block Context, so it runs in a Magento root (see phpunit.xml).
 */
class FormKeyProviderTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Context::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    public function testEmitsTheProviderScriptWhenFullPageCacheIsOn(): void
    {
        $resolver = $this->createMock(ViteResolver::class);
        $resolver->expects($this->once())
            ->method('getViteFileUrl')
            ->with('MageObsidian_Storefront::js/form-key-provider')
            ->willReturn('/static/generated/MageObsidian_Storefront/js/form-key-provider.js');

        $renderer = $this->createMock(SecureHtmlRenderer::class);
        $renderer->expects($this->once())
            ->method('renderTag')
            ->with(
                'script',
                [
                    'type' => 'module',
                    'src' => '/static/generated/MageObsidian_Storefront/js/form-key-provider.js',
                ],
                '',
                false
            )
            ->willReturn('<script type="module" src="/x.js" nonce="abc"></script>');

        $html = $this->render($resolver, $renderer, cacheEnabled: true);

        $this->assertSame('<script type="module" src="/x.js" nonce="abc"></script>', $html);
    }

    public function testEmitsNothingWhenFullPageCacheIsOff(): void
    {
        $resolver = $this->createMock(ViteResolver::class);
        $resolver->expects($this->never())->method('getViteFileUrl');

        $renderer = $this->createMock(SecureHtmlRenderer::class);
        $renderer->expects($this->never())->method('renderTag');

        $this->assertSame('', $this->render($resolver, $renderer, cacheEnabled: false));
    }

    public function testAsksTheStateForTheFullPageCacheFlag(): void
    {
        $cacheState = $this->createMock(StateInterface::class);
        $cacheState->expects($this->once())
            ->method('isEnabled')
            ->with('full_page')
            ->willReturn(false);

        $block = new FormKeyProvider(
            $this->createMock(Context::class),
            $this->createMock(ViteResolver::class),
            $this->createMock(SecureHtmlRenderer::class),
            $cacheState
        );

        (new ReflectionMethod($block, '_toHtml'))->invoke($block);
    }

    private function render(
        ViteResolver $resolver,
        SecureHtmlRenderer $renderer,
        bool $cacheEnabled
    ): string {
        $cacheState = $this->createMock(StateInterface::class);
        $cacheState->method('isEnabled')->willReturn($cacheEnabled);

        $block = new FormKeyProvider(
            $this->createMock(Context::class),
            $resolver,
            $renderer,
            $cacheState
        );

        return (string)(new ReflectionMethod($block, '_toHtml'))->invoke($block);
    }
}
