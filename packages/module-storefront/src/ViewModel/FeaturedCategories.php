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
 * Category tiles for the designed home: the store's top-level, in-menu
 * categories with their image, capped at a small count. Consumed from Twig as
 * `block.getFeatured().getItems()`. Shares the nav's category source, adding the
 * image; on a store with no menu categories (or any failure) it returns an empty
 * list and the home simply renders without the grid.
 */
class FeaturedCategories implements ArgumentInterface
{
    /**
     * @param CollectionFactory $categoryCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param int $limit
     */
    public function __construct(
        private readonly CollectionFactory $categoryCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly int $limit = 4
    ) {
    }

    /**
     * Top-level menu categories as tiles, capped at the configured limit.
     *
     * @return array<int, array{label: string, url: string, image: string}>
     */
    public function getItems(): array
    {
        try {
            return $this->loadCategories();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Load top-level, in-menu categories with their image, capped at the limit.
     *
     * @return array<int, array{label: string, url: string, image: string}>
     */
    private function loadCategories(): array
    {
        $rootCategoryId = (int)$this->storeManager->getStore()->getRootCategoryId();

        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'url_key', 'url_path', 'image'])
            ->addAttributeToFilter('parent_id', $rootCategoryId)
            ->addAttributeToFilter('is_active', 1)
            ->addAttributeToFilter('include_in_menu', 1)
            ->setOrder('position', 'ASC')
            ->setPageSize($this->limit);

        $items = [];
        foreach ($collection as $category) {
            $items[] = [
                'label' => (string)$category->getName(),
                'url' => (string)$category->getUrl(),
                'image' => (string)$category->getImageUrl(),
            ];
        }

        return $items;
    }
}
