<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Catalog\Model\Weee;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Throwable;

/**
 * Answers the platform's per-product fixed-product-tax lookup for a whole page
 * in one query.
 *
 * Magento's own API is per entity and its cache is keyed the same way, so a
 * listing asks the database once per product for rows that differ only by that
 * product. What is warmed here is exactly the same set, in one statement, for
 * the ids a collection just loaded.
 *
 * The statement is a copy of the platform's, which is the price of this: the
 * platform exposes no batch form, so the SELECT has to be reproduced and kept in
 * step by hand. Two things keep the risk bounded — the copy is ordered and
 * filtered identically, and every id the warm pass did not answer for falls
 * through to the platform's own method rather than being reported as having no
 * tax. A wrong answer here is a wrong price, so the fall-through is the point.
 *
 * @see \Magento\Weee\Model\ResourceModel\Tax::fetchWeeeTaxCalculationsByEntity
 */
class TaxBatch
{
    /**
     * Entity ids a collection loaded and nothing has asked about yet.
     *
     * @var int[]
     */
    private array $pending = [];

    /**
     * "<scope>" => ["<entityId>" => rows]
     *
     * @var array<string, array<int, array<int, array<string, mixed>>>>
     */
    private array $warmed = [];

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @param int[] $entityIds
     *
     * @return void
     */
    public function expect(array $entityIds): void
    {
        foreach ($entityIds as $entityId) {
            $id = (int)$entityId;
            if ($id > 0) {
                $this->pending[$id] = $id;
            }
        }
    }

    /**
     * The rows for one entity, or null when this has nothing to say about it —
     * which is the caller's cue to ask the platform.
     *
     * @param string $countryId
     * @param string $regionId
     * @param int $websiteId
     * @param int $storeId
     * @param int $entityId
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function rowsFor(
        string $countryId,
        string $regionId,
        int $websiteId,
        int $storeId,
        int $entityId
    ): ?array {
        $scope = implode('-', [$countryId, $regionId, $websiteId, $storeId]);

        if (!isset($this->warmed[$scope][$entityId]) && $this->pending !== []) {
            $this->warm($scope, $countryId, $regionId, $websiteId, $storeId, array_values($this->pending));
        }

        return $this->warmed[$scope][$entityId] ?? null;
    }

    /**
     * @param int[] $entityIds
     *
     * @return void
     */
    private function warm(
        string $scope,
        string $countryId,
        string $regionId,
        int $websiteId,
        int $storeId,
        array $entityIds
    ): void {
        // Cleared first: a warm pass that fails must not be retried once per
        // product, which would cost more than the queries it set out to save.
        $this->pending = [];

        try {
            $connection = $this->resource->getConnection();
            $select = $connection->select()
                ->from(
                    ['eavTable' => $this->resource->getTableName('eav_attribute')],
                    ['eavTable.attribute_code', 'eavTable.attribute_id', 'eavTable.frontend_label']
                )
                ->joinLeft(
                    ['eavLabel' => $this->resource->getTableName('eav_attribute_label')],
                    'eavLabel.attribute_id = eavTable.attribute_id and eavLabel.store_id = ' . $storeId,
                    'eavLabel.value as label_value'
                )
                ->joinInner(
                    ['weeeTax' => $this->resource->getTableName('weee_tax')],
                    'weeeTax.attribute_id = eavTable.attribute_id',
                    ['weeeTax.value as weee_value', 'weeeTax.entity_id']
                )
                ->where('eavTable.frontend_input = ?', 'weee')
                ->where('weeeTax.website_id IN(?)', [$websiteId, 0])
                ->where('weeeTax.country = ?', $countryId)
                ->where('weeeTax.state IN(?)', [$regionId, 0])
                ->where('weeeTax.entity_id IN(?)', $entityIds)
                ->order(['weeeTax.state ' . Select::SQL_DESC, 'weeeTax.website_id ' . Select::SQL_DESC]);

            $rows = $connection->fetchAll($select);
        } catch (Throwable) {
            // The platform's own method is still there; saying nothing is the
            // only safe failure for something that decides a price.
            return;
        }

        $byEntity = array_fill_keys($entityIds, []);
        foreach ($rows as $row) {
            $entityId = (int)($row['entity_id'] ?? 0);
            unset($row['entity_id']);
            $byEntity[$entityId][] = $row;
        }

        $this->warmed[$scope] = ($this->warmed[$scope] ?? []) + $byEntity;
    }
}
