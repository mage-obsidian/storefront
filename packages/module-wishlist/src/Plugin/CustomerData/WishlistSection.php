<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Wishlist project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Wishlist\Plugin\CustomerData;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Wishlist\CustomerData\Wishlist;
use Magento\Wishlist\Helper\Data as WishlistHelper;
use Throwable;

/**
 * Reshapes the wishlist section for the storefront islands. Two things: the
 * native `items` render each product price through `product.price.render.default`,
 * which the engine suppresses (so a non-empty wishlist 400s the section); and the
 * heart needs every membership, while native `items` caps at 3. We fall back to a
 * price-free payload on that failure and always attach `saved` (product id →
 * remove url) — the only wishlist data the islands read.
 */
class WishlistSection
{
    /**
     * @param WishlistHelper $wishlistHelper
     * @param UrlInterface $url
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly WishlistHelper $wishlistHelper,
        private readonly UrlInterface $url,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Reshape the wishlist section: price-free fallback plus the `saved` map.
     *
     * @param Wishlist $subject
     * @param callable $proceed
     * @return array
     */
    public function aroundGetSectionData(Wishlist $subject, callable $proceed): array
    {
        try {
            $result = $proceed();
        } catch (Throwable) {
            $result = [
                'counter' => null,
                'items' => [],
                'websiteId' => $this->storeManager->getWebsite()->getId(),
                'storeId' => $this->storeManager->getStore()->getId(),
            ];
        }
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
