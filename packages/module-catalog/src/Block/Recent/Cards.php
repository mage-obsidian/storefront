<?php
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Catalog\Block\Recent;

use Magento\Catalog\Block\Product\AbstractProduct;

class Cards extends AbstractProduct
{
    public function getProducts(): array
    {
        return (array)$this->getData('products');
    }
}
