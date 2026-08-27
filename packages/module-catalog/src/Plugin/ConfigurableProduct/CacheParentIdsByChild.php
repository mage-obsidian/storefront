<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Catalog\Plugin\ConfigurableProduct;

use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;

/**
 * Answers a repeated parent-ids lookup from memory for the rest of the request.
 *
 * The resource model runs the join every time it is asked, and the Weee plugin
 * on Tax::getProductWeeeAttributes asks once per product whose own FPT value is
 * empty — twice per product on a listing, because the price is rendered twice.
 * Measured on a twelve-product category page: 23 identical queries for 12
 * distinct children.
 *
 * A batch call is passed straight through: it returns a flat list of parent ids
 * with no child to attribute them to, so there is nothing to remember.
 */
class CacheParentIdsByChild
{
    /**
     * @var array<int, string[]>
     */
    private array $parents = [];

    /**
     * @param Configurable $subject
     * @param callable $proceed
     * @param int|string|array $childId
     *
     * @return string[]
     */
    public function aroundGetParentIdsByChild(Configurable $subject, callable $proceed, $childId): array
    {
        if (is_array($childId)) {
            return $proceed($childId);
        }

        $id = (int)$childId;
        if (!array_key_exists($id, $this->parents)) {
            $this->parents[$id] = $proceed($childId);
        }

        return $this->parents[$id];
    }
}
