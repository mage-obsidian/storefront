<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Model\Category;

use Magento\Catalog\Model\Category;
use Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

/**
 * Resolves the rewrites of a whole set of categories in one query.
 *
 * Magento\Catalog\Model\Category::getUrl() returns early when the category already
 * carries a request_path, and only falls back to a per-category
 * UrlFinder::findOneByData() when it does not. Seeding that field up front turns
 * what was one url_rewrite query per category — 150 of the 207 queries on a
 * category page with a 300-category tree, the single largest source on the page —
 * into one.
 */
class RequestPathResolver
{
    /**
     * @param UrlFinderInterface $urlFinder
     */
    public function __construct(private readonly UrlFinderInterface $urlFinder)
    {
    }

    /**
     * Seed request_path on every category that has a rewrite in this store.
     *
     * Categories with no rewrite are left untouched on purpose: getUrl() then takes
     * its own fallback and still returns a working id-based URL.
     *
     * @param array<int, Category> $categoriesById
     * @param int $storeId
     * @return void
     */
    public function seed(array $categoriesById, int $storeId): void
    {
        if ($categoriesById === []) {
            return;
        }

        $rewrites = $this->urlFinder->findAllByData([
            UrlRewrite::ENTITY_ID => array_keys($categoriesById),
            UrlRewrite::ENTITY_TYPE => CategoryUrlRewriteGenerator::ENTITY_TYPE,
            UrlRewrite::STORE_ID => $storeId,
            UrlRewrite::REDIRECT_TYPE => 0,
        ]);

        // First match wins, because that is what getUrl() would have picked: its
        // findOneByData() ends in a fetchRow() with no ORDER BY. Letting the last row
        // win instead would silently pick a different path from core whenever a
        // category has more than one non-redirect rewrite.
        $seen = [];
        foreach ($rewrites as $rewrite) {
            $entityId = (int)$rewrite->getEntityId();
            if (isset($seen[$entityId]) || !isset($categoriesById[$entityId])) {
                continue;
            }
            $seen[$entityId] = true;
            $categoriesById[$entityId]->setData('request_path', $rewrite->getRequestPath());
        }
    }
}
