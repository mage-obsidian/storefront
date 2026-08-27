<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Catalog\Plugin\Weee;

use Magento\Weee\Model\ResourceModel\Tax;
use MageObsidian\Catalog\Model\Weee\TaxBatch;

/**
 * Answers the fixed-product-tax lookup from the page's own warm pass when it
 * can, and lets the platform answer when it cannot.
 *
 * The platform's method already caches per entity; what it cannot do is answer
 * for a product it has not been asked about yet, which on a listing is every
 * product.
 */
class BatchTaxByEntity
{
    /**
     * @param TaxBatch $batch
     */
    public function __construct(
        private readonly TaxBatch $batch
    ) {
    }

    /**
     * @param Tax $subject
     * @param callable $proceed
     * @param string|int $countryId
     * @param string|int $regionId
     * @param int $websiteId
     * @param int $storeId
     * @param int $entityId
     *
     * @return array
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function aroundFetchWeeeTaxCalculationsByEntity(
        Tax $subject,
        callable $proceed,
        $countryId,
        $regionId,
        $websiteId,
        $storeId,
        $entityId
    ): array {
        $rows = $this->batch->rowsFor(
            (string)$countryId,
            (string)$regionId,
            (int)$websiteId,
            (int)$storeId,
            (int)$entityId
        );

        return $rows ?? $proceed($countryId, $regionId, $websiteId, $storeId, $entityId);
    }
}
