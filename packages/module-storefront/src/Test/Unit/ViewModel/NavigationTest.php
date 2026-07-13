<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\ViewModel;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Storefront\ViewModel\Navigation;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The first ViewModel: feeds the header and mobile menu the same nav items from
 * a single source (no more duplicated nav_links). Maps the store's top-level
 * menu categories, and falls back to a demo list when the catalog has none —
 * so a fresh store still renders a usable header. Needs Magento Catalog types,
 * so it runs in a Magento root (see phpunit.ci.xml).
 */
class NavigationTest extends TestCase
{
    private CollectionFactory&MockObject $collectionFactory;
    private StoreManagerInterface&MockObject $storeManager;

    protected function setUp(): void
    {
        if (!class_exists(Category::class)) {
            $this->markTestSkipped('Magento Catalog is not available in this runtime.');
        }
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);

        $store = $this->createMock(Store::class);
        $store->method('getRootCategoryId')->willReturn(2);
        $this->storeManager->method('getStore')->willReturn($store);
    }

    /**
     * One collection per BFS level: each call to create() returns the next level's
     * categories, so a maxDepth=N test supplies N category lists in order.
     *
     * @param array<int, array<int, Category&MockObject>> $levels
     */
    private function collectionsReturning(array $levels): void
    {
        $collections = [];
        foreach ($levels as $categories) {
            $collection = $this->createMock(Collection::class);
            $collection->method('addAttributeToSelect')->willReturnSelf();
            $collection->method('addAttributeToFilter')->willReturnSelf();
            $collection->method('setOrder')->willReturnSelf();
            $collection->method('getIterator')->willReturn(new \ArrayIterator($categories));
            $collections[] = $collection;
        }
        $this->collectionFactory->method('create')->willReturnOnConsecutiveCalls(...$collections);
    }

    /**
     * @param array<int, Category&MockObject> $categories
     */
    private function collectionReturning(array $categories): void
    {
        $this->collectionsReturning([$categories]);
    }

    private function category(string $name, string $url, int $id = 0, int $parentId = 2): Category&MockObject
    {
        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn($id);
        $category->method('getParentId')->willReturn($parentId);
        $category->method('getName')->willReturn($name);
        $category->method('getUrl')->willReturn($url);

        return $category;
    }

    private function subject(): Navigation
    {
        return new Navigation($this->collectionFactory, $this->storeManager);
    }

    public function testMapsTopCategoriesToNavItems(): void
    {
        $this->collectionReturning([
            $this->category('Outerwear', 'https://shop.test/outerwear'),
            $this->category('Tailoring', 'https://shop.test/tailoring'),
        ]);

        $items = $this->subject()->getItems();

        $this->assertCount(2, $items);
        $this->assertSame('Outerwear', $items[0]['label']);
        $this->assertSame('https://shop.test/outerwear', $items[0]['url']);
        $this->assertArrayHasKey('active', $items[0]);
    }

    public function testBuildsNestedTreeUpToMaxDepth(): void
    {
        $this->collectionsReturning([
            [
                $this->category('Outerwear', 'https://shop.test/outerwear', 10, 2),
                $this->category('Tailoring', 'https://shop.test/tailoring', 11, 2),
            ],
            [
                $this->category('Coats', 'https://shop.test/coats', 20, 10),
                $this->category('Jackets', 'https://shop.test/jackets', 21, 10),
            ],
        ]);

        $items = $this->subject()->getItems(2);

        $this->assertCount(2, $items);
        $this->assertSame('Outerwear', $items[0]['label']);
        $this->assertArrayHasKey('children', $items[0]);
        $this->assertSame(['Coats', 'Jackets'], array_column($items[0]['children'], 'label'));
        // A leaf category carries no children key.
        $this->assertArrayNotHasKey('children', $items[1]);
    }

    public function testDefaultDepthKeepsTopLevelOnlyWithoutChildren(): void
    {
        $this->collectionReturning([
            $this->category('Outerwear', 'https://shop.test/outerwear', 10, 2),
        ]);

        $items = $this->subject()->getItems();

        $this->assertCount(1, $items);
        $this->assertArrayNotHasKey('children', $items[0]);
    }

    public function testFallsBackToDemoItemsWhenCatalogHasNoMenuCategories(): void
    {
        $this->collectionReturning([]);

        $items = $this->subject()->getItems();

        $this->assertNotEmpty($items);
        $this->assertContainsOnly('array', $items);
        foreach ($items as $item) {
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('url', $item);
        }
    }
}
