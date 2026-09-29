<?php
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Catalog\Plugin\Catalog;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use MageObsidian\Catalog\Model\Weee\TaxBatch;

/**
 * Tells the fixed-product-tax warm pass which products a page is about.
 *
 * The ids are only recorded here, never queried: a listing whose products carry
 * no fixed product tax — most listings — must not pay for a warm pass nobody
 * asked for. The query happens the first time something actually asks about one
 * of them.
 */
class ExpectWeeeTaxForCollection
{
    /**
     * @param TaxBatch $batch
     */
    public function __construct(
        private readonly TaxBatch $batch
    ) {
    }

    /**
     * @param Collection $subject
     * @param Collection $result
     *
     * @return Collection
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterLoad(Collection $subject, $result)
    {
        $this->batch->expect($subject->getLoadedIds());

        return $result;
    }
}
