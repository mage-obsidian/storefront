<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Test\Unit\Plugin\Cms;

use Magento\Cms\Block\Block as CmsBlock;
use Magento\Cms\Block\Page as CmsPage;
use MageObsidian\Storefront\Plugin\Cms\WrapAuthoredContent;
use PHPUnit\Framework\TestCase;

/**
 * The wrapper is what the theme's prose styles hook onto, so what matters is
 * that it is always there for real content and never there for none — an empty
 * `<div class="cms-content">` on the designed home would add a stray box to a
 * page that renders no CMS content at all.
 */
class WrapAuthoredContentTest extends TestCase
{
    private WrapAuthoredContent $plugin;

    protected function setUp(): void
    {
        $this->plugin = new WrapAuthoredContent();
    }

    public function testWrapsPageContent(): void
    {
        $subject = $this->createMock(CmsPage::class);

        $this->assertSame(
            '<div class="cms-content"><h2>Privacy</h2></div>',
            $this->plugin->afterToHtml($subject, '<h2>Privacy</h2>')
        );
    }

    public function testWrapsBlockContentTheSameWay(): void
    {
        $subject = $this->createMock(CmsBlock::class);

        $this->assertStringStartsWith(
            '<div class="' . WrapAuthoredContent::CONTENT_CLASS . '">',
            $this->plugin->afterToHtml($subject, '<p>Free shipping</p>')
        );
    }

    public function testLeavesEmptyOutputAlone(): void
    {
        $subject = $this->createMock(CmsPage::class);

        $this->assertSame('', $this->plugin->afterToHtml($subject, ''));
        $this->assertSame("\n  \n", $this->plugin->afterToHtml($subject, "\n  \n"));
    }
}
