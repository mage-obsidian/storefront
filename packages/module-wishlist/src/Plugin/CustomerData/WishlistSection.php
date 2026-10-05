<?php
/**
 * This file is part of the MageObsidian - Wishlist project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Wishlist\Plugin\CustomerData;

use Magento\Framework\UrlInterface;
use Magento\Wishlist\CustomerData\Wishlist;
use Magento\Wishlist\Helper\Data as WishlistHelper;
use Throwable;

/**
 * Attaches `saved` (product id → remove url) to the wishlist section so the
 * heart reflects every membership; the native `items` cap at three. (Price
 * rendering itself is handled now that product.price.render.default is restored.)
 */
class WishlistSection
{
    /**
     * @param WishlistHelper $wishlistHelper
     * @param UrlInterface $url
     */
    public function __construct(
        private readonly WishlistHelper $wishlistHelper,
        private readonly UrlInterface $url
    ) {
    }

    /**
     * Attach the `saved` map to the section payload.
     *
     * @param Wishlist $subject
     * @param array $result
     * @return array
     */
    public function afterGetSectionData(Wishlist $subject, array $result): array
    {
        $result['saved'] = $this->buildSaved();

        return $result;
    }

    /**
     * Map every saved product id to its native remove url.
     *
     * @return array<int, string>
     */
    private function buildSaved(): array
    {
        $saved = [];
        try {
            foreach ($this->wishlistHelper->getWishlistItemCollection() as $item) {
                $saved[(int)$item->getProductId()] = $this->url->getUrl(
                    'wishlist/index/remove',
                    ['item' => (int)$item->getId()]
                );
            }
        } catch (Throwable) {
            $saved = [];
        }

        return $saved;
    }
}
