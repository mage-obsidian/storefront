<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\ViewModel;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Storefront\ViewModel\FeaturedCategories;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Feeds the designed home its category tiles: the store's top-level menu
 * categories with an image, capped at a small count. Maps the same source as
 * the nav, plus the image; empty when the catalog has no menu categories (the
 * home simply renders without the grid). Needs Magento Catalog types.
 */
class FeaturedCategoriesTest extends TestCase
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
     * @param array<int, Category&MockObject> $categories
     */
    private function collectionReturning(array $categories): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('addAttributeToFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator($categories));
        $this->collectionFactory->method('create')->willReturn($collection);
    }

    private function category(string $name, string $url, string $image): Category&MockObject
    {
        $category = $this->createMock(Category::class);
        $category->method('getName')->willReturn($name);
        $category->method('getUrl')->willReturn($url);
        $category->method('getImageUrl')->willReturn($image);

        return $category;
    }

    private function subject(int $limit = 4): FeaturedCategories
    {
        return new FeaturedCategories($this->collectionFactory, $this->storeManager, $limit);
    }

    public function testMapsTopCategoriesWithImages(): void
    {
        $this->collectionReturning([
            $this->category('Women', 'https://shop.test/women', 'https://shop.test/media/women.jpg'),
            $this->category('Men', 'https://shop.test/men', ''),
        ]);

        $items = $this->subject()->getItems();

        $this->assertCount(2, $items);
        $this->assertSame('Women', $items[0]['label']);
        $this->assertSame('https://shop.test/women', $items[0]['url']);
        $this->assertSame('https://shop.test/media/women.jpg', $items[0]['image']);
        $this->assertSame('', $items[1]['image']);
    }

    public function testReturnsEmptyWhenNoCategories(): void
    {
        $this->collectionReturning([]);

        $this->assertSame([], $this->subject()->getItems());
    }
}
