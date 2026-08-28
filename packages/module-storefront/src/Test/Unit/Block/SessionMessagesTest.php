<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Test\Unit\Block;

use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;
use MageObsidian\Storefront\Block\SessionMessages;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Needs Magento's block Context, so it runs in a Magento root (see phpunit.xml).
 */
class SessionMessagesTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Context::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    public function testEmitsTheSessionMessagesScript(): void
    {
        $resolver = $this->createMock(ViteResolver::class);
        $resolver->expects($this->once())
            ->method('getViteFileUrl')
            ->with('MageObsidian_Storefront::js/session-messages')
            ->willReturn('/static/generated/MageObsidian_Storefront/js/session-messages.js');

        $renderer = $this->createMock(SecureHtmlRenderer::class);
        $renderer->expects($this->once())
            ->method('renderTag')
            ->with(
                'script',
                [
                    'type' => 'module',
                    'src' => '/static/generated/MageObsidian_Storefront/js/session-messages.js',
                    'fetchpriority' => 'low',
                ],
                '',
                false
            )
            ->willReturn('<script type="module" src="/x.js"></script>');

        $block = new SessionMessages($this->createMock(Context::class), $resolver, $renderer);

        $this->assertSame(
            '<script type="module" src="/x.js"></script>',
            (string)(new ReflectionMethod($block, '_toHtml'))->invoke($block)
        );
    }
}
