<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

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
