<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\ViewModel;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

/**
 * Single source of the main navigation, consumed from Twig as
 * `block.getNavigation().getItems()` (registered as a layout `<argument>`). The
 * header and the mobile menu both read it, so the nav lives in one place.
 *
 * Maps the store's top-level menu categories; on a store with no menu categories
 * (or any failure) it falls back to a demo list so the header still renders.
 *
 * @phpstan-type NavItem array{label: string, url: string, active: bool, children?: array<int, mixed>}
 */
class Navigation implements ArgumentInterface
{
    /** @var array<int, array{label: string, url: string, active: bool}> */
    private const DEMO_ITEMS = [
        ['label' => 'New in', 'url' => '#', 'active' => false],
        ['label' => 'Outerwear', 'url' => '#', 'active' => false],
        ['label' => 'Tailoring', 'url' => '#', 'active' => false],
        ['label' => 'The Vitreous Edit', 'url' => '#', 'active' => false],
        ['label' => 'Archive', 'url' => '#', 'active' => false],
    ];

    /**
     * @param CollectionFactory $categoryCollectionFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly CollectionFactory $categoryCollectionFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Main navigation items, from the store's menu categories or a demo fallback.
     *
     * With $maxDepth > 1 each item may carry a nested `children` list (same shape,
     * recursive) so a theme can build a mega menu; the default keeps the previous
     * top-level-only output, so existing consumers (footer, mobile) are unchanged.
     *
     * @param int $maxDepth Levels of menu categories to load (1 = top level only).
     * @return array<int, NavItem>
     */
    public function getItems(int $maxDepth = 1): array
    {
        try {
            $items = $this->loadMenuTree(max(1, $maxDepth));
        } catch (Throwable) {
            $items = [];
        }

        return $items !== [] ? $items : self::DEMO_ITEMS;
    }

    /**
     * Load the store's in-menu categories under the root, down to $maxDepth, as a
     * nested tree. One collection per level (BFS, bounded by $maxDepth) keyed by
     * parent, so there is no per-category query and no full-catalog scan.
     *
     * @param int $maxDepth
     * @return array<int, NavItem>
     */
    private function loadMenuTree(int $maxDepth): array
    {
        $rootCategoryId = (int)$this->storeManager->getStore()->getRootCategoryId();

        $parentIds = [$rootCategoryId];
        $nodesByParent = [];
        for ($depth = 0; $depth < $maxDepth && $parentIds !== []; $depth++) {
            $collection = $this->categoryCollectionFactory->create();
            $collection->addAttributeToSelect(['name', 'url_key', 'url_path'])
                ->addAttributeToFilter('parent_id', ['in' => $parentIds])
                ->addAttributeToFilter('is_active', 1)
                ->addAttributeToFilter('include_in_menu', 1)
                ->setOrder('position', 'ASC');

            $nextParentIds = [];
            foreach ($collection as $category) {
                $id = (int)$category->getId();
                $nodesByParent[(int)$category->getParentId()][] = [
                    'id' => $id,
                    'label' => (string)$category->getName(),
                    'url' => (string)$category->getUrl(),
                ];
                $nextParentIds[] = $id;
            }
            $parentIds = $nextParentIds;
        }

        return $this->assembleTree($rootCategoryId, $nodesByParent);
    }

    /**
     * Turn the parent-keyed node buckets into a nested nav tree.
     *
     * @param int $parentId
     * @param array<int, array<int, array{id: int, label: string, url: string}>> $nodesByParent
     * @return array<int, NavItem>
     */
    private function assembleTree(int $parentId, array $nodesByParent): array
    {
        $items = [];
        foreach ($nodesByParent[$parentId] ?? [] as $node) {
            $item = ['label' => $node['label'], 'url' => $node['url'], 'active' => false];
            $children = $this->assembleTree($node['id'], $nodesByParent);
            if ($children !== []) {
                $item['children'] = $children;
            }
            $items[] = $item;
        }

        return $items;
    }
}
