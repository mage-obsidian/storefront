<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\ViewModel;

use Magento\Catalog\Model\Category;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Storefront\Model\Navigation\MenuTree;
use MageObsidian\Storefront\ViewModel\Navigation;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Feeds the header, the mobile drawer and the footer the same nav items from a
 * single source (no more duplicated nav_links). The tree comes from MenuTree;
 * what is tested here is the presentation contract around it — the per-request
 * memo and the demo fallback that keeps a fresh store's header usable. Needs
 * Magento Catalog types, so it runs in a Magento root (see phpunit.ci.xml).
 */
class NavigationTest extends TestCase
{
    private MenuTree&MockObject $menuTree;
    private StoreManagerInterface&MockObject $storeManager;

    protected function setUp(): void
    {
        if (!class_exists(Category::class)) {
            $this->markTestSkipped('Magento Catalog is not available in this runtime.');
        }
        $this->menuTree = $this->createMock(MenuTree::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);

        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $this->storeManager->method('getStore')->willReturn($store);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function treeReturning(array $items): void
    {
        $this->menuTree->method('get')->willReturn($items);
    }

    private function subject(): Navigation
    {
        return new Navigation($this->menuTree, $this->storeManager);
    }

    public function testExposesTheMenuTreeAsNavItems(): void
    {
        $this->treeReturning([
            ['label' => 'Outerwear', 'url' => 'https://shop.test/outerwear', 'active' => false],
        ]);

        $items = $this->subject()->getItems();

        $this->assertCount(1, $items);
        $this->assertSame('Outerwear', $items[0]['label']);
        $this->assertSame('https://shop.test/outerwear', $items[0]['url']);
        $this->assertArrayHasKey('active', $items[0]);
    }

    public function testAsksForTheRequestedDepth(): void
    {
        $this->menuTree->expects($this->once())
            ->method('get')
            ->with(2)
            ->willReturn([]);

        $this->subject()->getItems(2);
    }

    public function testTreatsAnyDepthBelowOneAsTopLevel(): void
    {
        $this->menuTree->expects($this->once())
            ->method('get')
            ->with(1)
            ->willReturn([]);

        $this->subject()->getItems(0);
    }

    /**
     * The header, the mobile drawer and the footer each ask for the nav, and layout
     * object arguments are shared, so they all land on this same instance. Without
     * the memo the tree was resolved once per consumer.
     */
    public function testResolvesEachDepthOnlyOncePerRequest(): void
    {
        $this->menuTree->expects($this->exactly(2))
            ->method('get')
            ->willReturn([['label' => 'Outerwear', 'url' => '/o.html', 'active' => false]]);

        $subject = $this->subject();
        $subject->getItems(2);
        $subject->getItems(2);
        $subject->getItems(1);
        $subject->getItems(1);
    }

    public function testFallsBackToDemoItemsWhenCatalogHasNoMenuCategories(): void
    {
        $this->treeReturning([]);

        $this->assertDemoItems($this->subject()->getItems());
    }

    public function testFallsBackToDemoItemsWhenTheTreeFails(): void
    {
        $this->menuTree->method('get')->willThrowException(new RuntimeException('cache down'));

        $this->assertDemoItems($this->subject()->getItems());
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function assertDemoItems(array $items): void
    {
        $this->assertNotEmpty($items);
        $this->assertContainsOnly('array', $items);
        foreach ($items as $item) {
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('url', $item);
        }
    }
}
