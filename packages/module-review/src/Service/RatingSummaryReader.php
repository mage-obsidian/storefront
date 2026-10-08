<?php
/**
 * This file is part of the MageObsidian - Review project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Review\Service;

use Magento\Framework\App\ResourceConnection;

class RatingSummaryReader
{
    /**
     * @param ResourceConnection $resource
     */
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * @param list<int> $productIds
     * @param int $storeId
     * @return array<int, array{percent: int, count: int}>
     */
    public function read(array $productIds, int $storeId): array
    {
        if ($productIds === []) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                ['summary' => $this->resource->getTableName('review_entity_summary')],
                ['entity_pk_value', 'reviews_count', 'rating_summary']
            )
            ->join(
                ['entity' => $this->resource->getTableName('review_entity')],
                'entity.entity_id = summary.entity_type AND entity.entity_code = \'product\'',
                []
            )
            ->where('summary.entity_pk_value IN (?)', $productIds)
            ->where('summary.store_id = ?', $storeId);

        $summaries = [];
        foreach ($connection->fetchAll($select) as $row) {
            $summaries[(int)$row['entity_pk_value']] = [
                'percent' => (int)$row['rating_summary'],
                'count' => (int)$row['reviews_count'],
            ];
        }

        return $summaries;
    }
}
