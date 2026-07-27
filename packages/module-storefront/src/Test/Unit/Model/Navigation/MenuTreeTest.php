<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\Model\Navigation;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\App\Cache\Type\Block as BlockCache;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Storefront\Model\Category\RequestPathResolver;
use MageObsidian\Storefront\Model\Navigation\MenuTree;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The store's menu tree and its block_html cache. Magento's own Topmenu keeps the
 * rendered menu for an hour; this holds the same contract over the data. Needs
 * Magento Catalog and Store types, so it runs in a Magento root (see
 * phpunit.ci.xml).
 */
class MenuTreeTest extends TestCase
{
    private CollectionFactory&MockObject $collectionFactory;
    private StoreManagerInterface&MockObject $storeManager;
    private RequestPathResolver&MockObject $requestPathResolver;
    private BlockCache&MockObject $cache;
    private Json $serializer;

    protected function setUp(): void
    {
        if (!class_exists(Category::class)) {
            $this->markTestSkipped('Magento Catalog is not available in this runtime.');
        }
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->requestPathResolver = $this->createMock(RequestPathResolver::class);
        $this->cache = $this->createMock(BlockCache::class);
        $this->serializer = new Json();

        $this->store();
    }

    private function store(string $code = 'default', string $baseUrl = 'https://shop.test/'): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getCode')->willReturn($code);
        $store->method('getBaseUrl')->willReturn($baseUrl);
        $store->method('getRootCategoryId')->willReturn(2);
        $this->storeManager->method('getStore')->willReturn($store);
    }

    /**
     * One collection per BFS level: each create() returns the next level, so a
     * depth-N test supplies N category lists in order.
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

    private function category(string $name, string $url, int $id, int $parentId = 2): Category&MockObject
    {
        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn($id);
        $category->method('getParentId')->willReturn($parentId);
        $category->method('getName')->willReturn($name);
        $category->method('getUrl')->willReturn($url);

        return $category;
    }

    private function subject(int $cacheLifetime = 3600): MenuTree
    {
        return new MenuTree(
            $this->collectionFactory,
            $this->storeManager,
            $this->requestPathResolver,
            $this->cache,
            $this->serializer,
            $cacheLifetime
        );
    }

    public function testMapsTopCategoriesToNavItems(): void
    {
        $this->collectionsReturning([[
            $this->category('Outerwear', 'https://shop.test/outerwear', 10),
            $this->category('Tailoring', 'https://shop.test/tailoring', 11),
        ]]);

        $items = $this->subject()->get(1);

        $this->assertCount(2, $items);
        $this->assertSame('Outerwear', $items[0]['label']);
        $this->assertSame('https://shop.test/outerwear', $items[0]['url']);
        $this->assertFalse($items[0]['active']);
        $this->assertArrayNotHasKey('children', $items[0]);
    }

    public function testBuildsNestedTreeUpToDepth(): void
    {
        $this->collectionsReturning([
            [
                $this->category('Outerwear', 'https://shop.test/outerwear', 10),
                $this->category('Tailoring', 'https://shop.test/tailoring', 11),
            ],
            [
                $this->category('Coats', 'https://shop.test/coats', 20, 10),
                $this->category('Jackets', 'https://shop.test/jackets', 21, 10),
            ],
        ]);

        $items = $this->subject()->get(2);

        $this->assertSame(['Coats', 'Jackets'], array_column($items[0]['children'], 'label'));
        // A leaf category carries no children key.
        $this->assertArrayNotHasKey('children', $items[1]);
    }

    public function testSeedsRequestPathsBeforeAskingForUrls(): void
    {
        $this->collectionsReturning([[
            $this->category('Outerwear', 'https://shop.test/outerwear', 10),
            $this->category('Tailoring', 'https://shop.test/tailoring', 11),
        ]]);

        $this->requestPathResolver->expects($this->once())
            ->method('seed')
            ->with(
                $this->callback(static fn (array $byId): bool => array_keys($byId) === [10, 11]),
                1
            );

        $this->subject()->get(1);
    }

    public function testServesACachedTreeWithoutTouchingTheCatalog(): void
    {
        $this->collectionFactory->expects($this->never())->method('create');
        $this->cache->expects($this->never())->method('save');
        $this->cache->method('load')->willReturn(
            $this->serializer->serialize([['label' => 'Outerwear', 'url' => '/o.html', 'active' => false]])
        );

        $this->assertSame('Outerwear', $this->subject()->get(1)[0]['label']);
    }

    /**
     * A rename emits only cat_c_<id>: Category::getIdentities() adds the generic
     * cat_c on create, delete or an include_in_menu change. Tagging with the generic
     * one alone would keep serving the old label until the TTL ran out.
     */
    public function testTagsEveryCategoryInTheTreeAndTheGenericTag(): void
    {
        $this->collectionsReturning([
            [$this->category('Outerwear', 'https://shop.test/outerwear', 10)],
            [$this->category('Coats', 'https://shop.test/coats', 20, 10)],
        ]);

        $this->cache->expects($this->once())
            ->method('save')
            ->with(
                $this->isType('string'),
                $this->isType('string'),
                ['cat_c_10', 'cat_c_20', 'cat_c'],
                3600
            );

        $this->subject()->get(2);
    }

    public function testHonoursAConfiguredLifetime(): void
    {
        $this->collectionsReturning([[$this->category('Outerwear', 'https://shop.test/o', 10)]]);

        $this->cache->expects($this->once())
            ->method('save')
            ->with($this->anything(), $this->anything(), $this->anything(), 120);

        $this->subject(120)->get(1);
    }

    /**
     * Url::_isSecure() returns true as soon as the request is secure, so the absolute
     * URLs in the tree differ between http and https. Sharing one entry would serve
     * http:// links on https:// pages.
     */
    public function testKeyDependsOnStoreBaseUrlAndDepth(): void
    {
        $keys = [];
        $this->cache->method('load')->willReturnCallback(function (string $key) use (&$keys): bool {
            $keys[] = $key;

            return false;
        });

        $this->collectionsReturning(array_fill(0, 6, []));

        $secure = $this->subject();
        $secure->get(1);
        $secure->get(2);

        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->store('default', 'http://shop.test/');
        $this->subject()->get(1);

        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->store('other', 'https://shop.test/');
        $this->subject()->get(1);

        $this->assertCount(4, $keys);
        $this->assertSame($keys, array_unique($keys));
    }

    public function testRebuildsWhenTheCachedPayloadIsNotATree(): void
    {
        $this->cache->method('load')->willReturn($this->serializer->serialize('corrupto'));
        $this->collectionsReturning([[$this->category('Outerwear', 'https://shop.test/o', 10)]]);
        $this->cache->expects($this->once())->method('save');

        $this->assertSame('Outerwear', $this->subject()->get(1)[0]['label']);
    }

    public function testCachesTheEmptyTreeOfAStoreWithNoMenuCategories(): void
    {
        $this->collectionsReturning([[]]);

        $this->cache->expects($this->once())
            ->method('save')
            ->with($this->anything(), $this->anything(), ['cat_c'], 3600);

        $this->assertSame([], $this->subject()->get(1));
    }
}
