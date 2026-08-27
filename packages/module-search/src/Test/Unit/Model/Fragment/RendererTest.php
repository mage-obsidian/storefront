<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Search project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Search\Test\Unit\Model\Fragment;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\LayoutInterface;
use MageObsidian\Search\Exception\FragmentUnavailableException;
use MageObsidian\Search\Model\Fragment\Renderer;
use MageObsidian\Search\Model\Fragment\SectionPool;
use PHPUnit\Framework\TestCase;

class RendererTest extends TestCase
{
    private const string ACTION = 'catalog_category_view';

    public function testItRendersEverySectionKeyedByName(): void
    {
        $layout = $this->createMock(LayoutInterface::class);
        $layout->method('getBlock')->willReturnMap([
            ['category.products.list', $this->block('<ol>grid</ol>')],
            ['catalog.leftnav', $this->block('<nav>filters</nav>')],
        ]);

        $renderer = new Renderer(new SectionPool([
            self::ACTION => ['listing' => 'category.products.list', 'filters' => 'catalog.leftnav'],
        ]));

        $this->assertSame(
            ['listing' => '<ol>grid</ol>', 'filters' => '<nav>filters</nav>'],
            $renderer->render($layout, self::ACTION)
        );
    }

    public function testAMissingBlockFailsTheWholeFragment(): void
    {
        $layout = $this->createMock(LayoutInterface::class);
        $layout->method('getBlock')->willReturnMap([
            ['category.products.list', $this->block('<ol>grid</ol>')],
            ['catalog.leftnav', false],
        ]);

        $renderer = new Renderer(new SectionPool([
            self::ACTION => ['listing' => 'category.products.list', 'filters' => 'catalog.leftnav'],
        ]));

        $this->expectException(FragmentUnavailableException::class);

        $renderer->render($layout, self::ACTION);
    }

    private function block(string $html): AbstractBlock
    {
        $block = $this->createMock(AbstractBlock::class);
        $block->method('toHtml')->willReturn($html);

        return $block;
    }
}
