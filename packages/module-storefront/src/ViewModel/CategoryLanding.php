<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\ViewModel;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Helper\Output as OutputHelper;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Throwable;

/**
 * Landing data for a category shown in "static block / page" display mode.
 *
 * MageObsidian suppresses the core catalog layout, and these top-of-nav
 * categories (Women/Men/Gear) ship as PAGE landings with no CMS block wired —
 * natively they would render empty. Instead of leaning on Luma's off-brand CMS
 * blocks, we surface the real category tree: each visible child becomes a tile
 * linking into the listing, illustrated by a representative product image (the
 * children carry no image of their own) and labelled with its visible product
 * count. Consumed from Twig as `landing.getItems()`. Any failure degrades to an
 * empty list so the page still renders.
 */
class CategoryLanding implements ArgumentInterface
{
    /**
     * Image id (see theme view.xml) used for the subcategory tile artwork.
     */
    private const TILE_IMAGE_ID = 'category_page_grid';

    /**
     * @param Registry $registry
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param ProductCollectionFactory $productCollectionFactory
     * @param Visibility $visibility
     * @param ImageHelper $imageHelper
     * @param OutputHelper $outputHelper
     */
    public function __construct(
        private readonly Registry $registry,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly Visibility $visibility,
        private readonly ImageHelper $imageHelper,
        private readonly OutputHelper $outputHelper
    ) {
    }

    /**
     * The current category's description, run through the catalog output filter
     * (WYSIWYG directives, widgets, allowed-html) so it is safe to print raw.
     * Empty string when there is no current category or no description.
     *
     * @return string
     */
    public function getDescriptionHtml(): string
    {
        try {
            $category = $this->registry->registry('current_category');
            if (!$category instanceof CategoryInterface || !$category->getDescription()) {
                return '';
            }
            return (string)$this->outputHelper->categoryAttribute(
                $category,
                $category->getDescription(),
                'description'
            );
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Visible child categories of the current category, as navigable tiles.
     *
     * @return array<int, array{label: string, url: string, count: int, image: string}>
     */
    public function getItems(): array
    {
        try {
            $category = $this->registry->registry('current_category');
            if (!$category instanceof CategoryInterface) {
                return [];
            }
            return $this->buildTiles((int)$category->getId());
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Build a tile per active, in-menu child of the given parent category.
     *
     * @param int $parentId
     * @return array<int, array{label: string, url: string, count: int, image: string}>
     */
    private function buildTiles(int $parentId): array
    {
        // is_anchor must be selected: addCategoryFilter() reads it off the child
        // to decide whether to count direct assignments only or the aggregated
        // (descendant-inclusive) index — without it anchor categories report 0.
        $children = $this->categoryCollectionFactory->create();
        $children->addAttributeToSelect(['name', 'url_key', 'url_path', 'is_anchor'])
            ->addAttributeToFilter('parent_id', $parentId)
            ->addAttributeToFilter('is_active', 1)
            ->addAttributeToFilter('include_in_menu', 1)
            ->setOrder('position', 'ASC');

        $tiles = [];
        foreach ($children as $child) {
            [$count, $image] = $this->representativeProducts($child);
            $tiles[] = [
                'label' => (string)$child->getName(),
                'url' => (string)$child->getUrl(),
                'count' => $count,
                'image' => $image,
            ];
        }

        return $tiles;
    }

    /**
     * Visible product count and a representative image URL for a category.
     *
     * Anchor categories (e.g. Women > Tops) aggregate their descendants because
     * addCategoryFilter resolves through the category/product index. The count
     * drives the tile label; the first product supplies the artwork since the
     * child categories carry no image of their own.
     *
     * @param CategoryInterface $category
     * @return array{0: int, 1: string}
     */
    private function representativeProducts(CategoryInterface $category): array
    {
        // Select the backing product image attributes (not the view id): the
        // image helper maps TILE_IMAGE_ID -> small_image via view.xml and reads
        // that attribute off the product, so it must be loaded or we fall back to
        // the placeholder.
        $products = $this->productCollectionFactory->create();
        $products->addCategoryFilter($category)
            ->setVisibility($this->visibility->getVisibleInCatalogIds())
            ->addAttributeToSelect(['image', 'small_image']);

        $count = $products->getSize();
        if ($count === 0) {
            return [0, ''];
        }

        $products->setPageSize(1)->setCurPage(1);
        $first = $products->getFirstItem();
        $image = $first->getId()
            ? (string)$this->imageHelper->init($first, self::TILE_IMAGE_ID)->getUrl()
            : '';

        return [$count, $image];
    }
}
