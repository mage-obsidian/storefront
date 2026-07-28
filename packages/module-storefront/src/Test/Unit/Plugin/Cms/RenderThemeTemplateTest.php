<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Test\Unit\Plugin\Cms;

use Magento\Cms\Block\Page as CmsPage;
use Magento\Framework\View\LayoutInterface;
use MageObsidian\ModernFrontend\Block\Template;
use MageObsidian\Storefront\Plugin\Cms\RenderThemeTemplate;
use PHPUnit\Framework\TestCase;

/**
 * The argument decides everything: with it the stored content is discarded, and
 * without it the plugin must be invisible — every CMS page in the store goes
 * through this method.
 */
class RenderThemeTemplateTest extends TestCase
{
    private RenderThemeTemplate $plugin;

    protected function setUp(): void
    {
        $this->plugin = new RenderThemeTemplate();
    }

    public function testRendersTheTemplateInsteadOfTheStoredContent(): void
    {
        $block = $this->createMock(Template::class);
        $block->expects($this->once())
            ->method('setTemplate')
            ->with('Magento_Cms::privacy/policy.twig')
            ->willReturnSelf();
        $block->method('toHtml')->willReturn('<h2>Privacy</h2>');

        $layout = $this->createMock(LayoutInterface::class);
        $layout->method('createBlock')->with(Template::class)->willReturn($block);

        $subject = $this->createMock(CmsPage::class);
        $subject->method('getLayout')->willReturn($layout);
        $subject->method('getData')
            ->with(RenderThemeTemplate::TEMPLATE_ARGUMENT)
            ->willReturn('Magento_Cms::privacy/policy.twig');

        $this->assertSame('<h2>Privacy</h2>', $this->plugin->afterToHtml($subject, '<p>From the database</p>'));
    }

    public function testKeepsTheStoredContentWithoutTheArgument(): void
    {
        $subject = $this->createMock(CmsPage::class);
        $subject->method('getData')->willReturn(null);
        $subject->expects($this->never())->method('getLayout');

        $this->assertSame('<p>From the database</p>', $this->plugin->afterToHtml($subject, '<p>From the database</p>'));
    }

    /**
     * @dataProvider unusableArguments
     */
    public function testIgnoresAnArgumentThatIsNotATemplateName(mixed $argument): void
    {
        $subject = $this->createMock(CmsPage::class);
        $subject->method('getData')->willReturn($argument);
        $subject->expects($this->never())->method('getLayout');

        $this->assertSame('<p>Stored</p>', $this->plugin->afterToHtml($subject, '<p>Stored</p>'));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unusableArguments(): array
    {
        return [
            'empty string' => [''],
            'array' => [['Magento_Cms::privacy/policy.twig']],
            'boolean' => [true],
        ];
    }
}
