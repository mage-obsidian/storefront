<?php
/**
 * This file is part of the MageObsidian - Wishlist project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace MageObsidian\Wishlist\ViewModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Resized product image for the wish-list grid.
 *
 * The wrapper block is a generic MageObsidian Template (no catalog getImage), so
 * the Twig pulls the resized URL plus intrinsic width/height from here — the
 * dimensions let the template reserve space and avoid layout shift.
 */
class ProductThumbnail implements ArgumentInterface
{
    public function __construct(
        private readonly ImageHelper $imageHelper
    ) {
    }

    /**
     * @param ProductInterface $product
     * @param string $imageId
     * @return array{url: string, width: int, height: int, label: string}
     */
    public function get(ProductInterface $product, string $imageId = 'category_page_grid'): array
    {
        $helper = $this->imageHelper->init($product, $imageId);

        return [
            'url' => (string)$helper->getUrl(),
            'width' => (int)$helper->getWidth(),
            'height' => (int)$helper->getHeight(),
            'label' => (string)($helper->getLabel() ?: $product->getName()),
        ];
    }
}
