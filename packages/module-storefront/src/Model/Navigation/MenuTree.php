<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Model\Navigation;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\App\Cache\Type\Block as BlockCache;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Storefront\Model\Category\RequestPathResolver;

/**
 * The store's menu-category tree, cached across requests.
 *
 * Magento's own Magento\Theme\Block\Html\Topmenu never pays for this twice either:
 * it keeps its rendered HTML in block_html for an hour. Moving the menu to a
 * ViewModel is what exposed the cost, so the cache is reinstated here — over the
 * data rather than the markup, because the three consumers (desktop bar, mobile
 * drawer, footer) each render it differently, and because the blocks emit Vue
 * islands whose module graph the engine collects at render time.
 *
 * Sitting in block_html means the tree follows the switch and the
 * `cache:clean block_html` merchants already use for the native menu, and gets
 * invalidated by tag on category save for free — Magento\Framework\App\Cache
 * \FlushCacheByTags only cleans block_html and collections, so a dedicated cache
 * type would have to reimplement that.
 *
 * @phpstan-type NavItem array{label: string, url: string, active: bool, children?: array<int, mixed>}
 */
class MenuTree
{
    private const CACHE_PREFIX = 'mageobsidian_nav';

    /**
     * @param CollectionFactory $categoryCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param RequestPathResolver $requestPathResolver
     * @param BlockCache $cache
     * @param SerializerInterface $serializer
     * @param int $cacheLifetime Seconds; defaults to what Topmenu gives the native menu.
     */
    public function __construct(
        private readonly CollectionFactory $categoryCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly RequestPathResolver $requestPathResolver,
        private readonly BlockCache $cache,
        private readonly SerializerInterface $serializer,
        private readonly int $cacheLifetime = 3600
    ) {
    }

    /**
     * The current store's in-menu category tree, down to $depth levels.
     *
     * @param int $depth
     * @return array<int, NavItem>
     */
    public function get(int $depth): array
    {
        $store = $this->storeManager->getStore();
        $cacheKey = $this->cacheKey($store->getCode(), $store->getBaseUrl(), $depth);

        $cached = $this->cache->load($cacheKey);
        if (is_string($cached) && $cached !== '') {
            $items = $this->serializer->unserialize($cached);
            if (is_array($items)) {
                return $items;
            }
        }

        $rootCategoryId = (int)$store->getRootCategoryId();
        [$categoriesByParent, $categoriesById] = $this->collect($rootCategoryId, $depth);
        $this->requestPathResolver->seed($categoriesById, (int)$store->getId());
        $items = $this->assembleTree($rootCategoryId, $categoriesByParent);

        $this->cache->save(
            $this->serializer->serialize($items),
            $cacheKey,
            $this->cacheTags(array_keys($categoriesById)),
            $this->cacheLifetime
        );

        return $items;
    }

    /**
     * Everything the rendered URLs vary by has to be in the key.
     *
     * Magento\Framework\Url::_isSecure() returns true as soon as the request itself
     * is secure, so the absolute URLs baked into the tree differ between http and
     * https. Keying on the base URL splits those into their own entries — the same
     * thing Magento\Framework\View\Element\Template::getCacheKeyInfo() does for
     * block HTML, and it also retires the old entries when the base URL changes.
     *
     * @param string $storeCode
     * @param string $baseUrl
     * @param int $depth
     * @return string
     */
    private function cacheKey(string $storeCode, string $baseUrl, int $depth): string
    {
        return self::CACHE_PREFIX . '_' . sha1(implode('|', [$storeCode, $baseUrl, $depth]));
    }

    /**
     * Tagged per category, not just with the generic cat_c: Category::getIdentities()
     * only emits the generic tag on create, delete or an include_in_menu change, so a
     * plain rename would leave the menu showing the old label until the TTL ran out.
     * The generic tag still has to be there for the categories that do not exist yet.
     *
     * @param array<int, int> $categoryIds
     * @return array<int, string>
     */
    private function cacheTags(array $categoryIds): array
    {
        $tags = array_map(
            static fn (int $id): string => Category::CACHE_TAG . '_' . $id,
            $categoryIds
        );
        $tags[] = Category::CACHE_TAG;

        return $tags;
    }

    /**
     * Collect the in-menu categories under the root, one collection per level.
     *
     * BFS bounded by $depth and keyed by parent, so there is no per-category query
     * and no full-catalog scan.
     *
     * @param int $rootCategoryId
     * @param int $depth
     * @return array{0: array<int, array<int, Category>>, 1: array<int, Category>}
     */
    private function collect(int $rootCategoryId, int $depth): array
    {
        $parentIds = [$rootCategoryId];
        $categoriesByParent = [];
        $categoriesById = [];

        for ($level = 0; $level < $depth && $parentIds !== []; $level++) {
            $collection = $this->categoryCollectionFactory->create();
            $collection->addAttributeToSelect(['name', 'url_key', 'url_path'])
                ->addAttributeToFilter('parent_id', ['in' => $parentIds])
                ->addAttributeToFilter('is_active', 1)
                ->addAttributeToFilter('include_in_menu', 1)
                ->setOrder('position', 'ASC');

            $nextParentIds = [];
            foreach ($collection as $category) {
                $id = (int)$category->getId();
                $categoriesByParent[(int)$category->getParentId()][] = $category;
                $categoriesById[$id] = $category;
                $nextParentIds[] = $id;
            }
            $parentIds = $nextParentIds;
        }

        return [$categoriesByParent, $categoriesById];
    }

    /**
     * Turn the parent-keyed buckets into the nested nav tree.
     *
     * Nothing request-dependent may be produced here — the result is what gets
     * cached. `active` is a placeholder the theme resolves client-side; the day it
     * depends on the current category it has to be applied after the cache read.
     *
     * @param int $parentId
     * @param array<int, array<int, Category>> $categoriesByParent
     * @return array<int, NavItem>
     */
    private function assembleTree(int $parentId, array $categoriesByParent): array
    {
        $items = [];
        foreach ($categoriesByParent[$parentId] ?? [] as $category) {
            $item = [
                'label' => (string)$category->getName(),
                'url' => (string)$category->getUrl(),
                'active' => false,
            ];
            $children = $this->assembleTree((int)$category->getId(), $categoriesByParent);
            if ($children !== []) {
                $item['children'] = $children;
            }
            $items[] = $item;
        }

        return $items;
    }
}
