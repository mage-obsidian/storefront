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
     * @return array<int, array{label: string, url: string, active: bool}>
     */
    public function getItems(): array
    {
        try {
            $items = $this->loadMenuCategories();
        } catch (Throwable) {
            $items = [];
        }

        return $items !== [] ? $items : self::DEMO_ITEMS;
    }

    /**
     * Load the store's top-level, in-menu categories as nav items.
     *
     * @return array<int, array{label: string, url: string, active: bool}>
     */
    private function loadMenuCategories(): array
    {
        $rootCategoryId = (int)$this->storeManager->getStore()->getRootCategoryId();

        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'url_key', 'url_path'])
            ->addAttributeToFilter('parent_id', $rootCategoryId)
            ->addAttributeToFilter('is_active', 1)
            ->addAttributeToFilter('include_in_menu', 1)
            ->setOrder('position', 'ASC');

        $items = [];
        foreach ($collection as $category) {
            $items[] = [
                'label' => (string)$category->getName(),
                'url' => (string)$category->getUrl(),
                'active' => false,
            ];
        }

        return $items;
    }
}
