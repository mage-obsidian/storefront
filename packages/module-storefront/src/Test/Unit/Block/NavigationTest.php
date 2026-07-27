<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\Block;

use Magento\Catalog\Model\Category;
use Magento\Framework\View\Element\Template\Context;
use MageObsidian\ModernFrontend\ViewModel\Image;
use MageObsidian\ModernFrontend\ViewModel\SchemaOrg;
use MageObsidian\ModernFrontend\ViewModel\ViteResolver;
use MageObsidian\Storefront\Block\Navigation;
use MageObsidian\Storefront\Model\Navigation\MenuTree;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Host block for a navigation island. Its only job beyond the base Template is to
 * carry the categories' cache tags, so that giving it a layout `ttl` does not
 * produce a menu no category save can purge. Needs Magento and MageObsidian
 * engine types, so it runs in a Magento root (see phpunit.ci.xml).
 */
class NavigationTest extends TestCase
{
    private MenuTree&MockObject $menuTree;
    private Context&MockObject $context;

    protected function setUp(): void
    {
        if (!class_exists(Category::class) || !class_exists(ViteResolver::class)) {
            $this->markTestSkipped('Magento Catalog or the MageObsidian engine is not available.');
        }
        $this->menuTree = $this->createMock(MenuTree::class);
        $this->context = $this->createMock(Context::class);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function subject(array $data = []): Navigation
    {
        return new Navigation(
            $this->context,
            $this->createMock(ViteResolver::class),
            $this->createMock(SchemaOrg::class),
            $this->createMock(Image::class),
            $this->menuTree,
            $data
        );
    }

    public function testTagsEveryCategoryTheIslandRenders(): void
    {
        $this->menuTree->expects($this->once())
            ->method('getIdentities')
            ->with(2)
            ->willReturn(['cat_c_10', 'cat_c_20', 'cat_c']);

        $this->assertSame(['cat_c_10', 'cat_c_20', 'cat_c'], $this->subject(['max_depth' => 2])->getIdentities());
    }

    public function testDefaultsToTopLevelWhenTheLayoutSetsNoDepth(): void
    {
        $this->menuTree->expects($this->once())->method('getIdentities')->with(1)->willReturn([]);

        $this->subject()->getIdentities();
    }

    /**
     * The ViewModel falls back to a demo menu on the same failure, and a demo menu
     * has no categories to tag. Throwing here would take the whole page down.
     */
    public function testTagsNothingWhenTheTreeFails(): void
    {
        $this->menuTree->method('getIdentities')->willThrowException(new RuntimeException('cache down'));

        $this->assertSame([], $this->subject(['max_depth' => 2])->getIdentities());
    }
}
