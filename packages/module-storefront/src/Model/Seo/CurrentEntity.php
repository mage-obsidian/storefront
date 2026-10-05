<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\Model\Seo;

use Magento\Framework\DataObject;
use Magento\Framework\Registry;
use Throwable;

class CurrentEntity
{
    public const string PRODUCT_KEY = 'current_product';
    public const string CATEGORY_KEY = 'current_category';

    public function __construct(
        private readonly Registry $registry
    ) {
    }

    public function getProduct(): ?DataObject
    {
        return $this->fromRegistry(self::PRODUCT_KEY);
    }

    public function getCategory(): ?DataObject
    {
        return $this->fromRegistry(self::CATEGORY_KEY);
    }

    private function fromRegistry(string $key): ?DataObject
    {
        try {
            $value = $this->registry->registry($key);
        } catch (Throwable) {
            return null;
        }

        return $value instanceof DataObject ? $value : null;
    }
}
