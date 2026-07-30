<?php
/**
 * This file is part of the MageObsidian - Sales project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\Sales\ViewModel;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Backs the guest "Orders and Returns" lookup island.
 *
 * The form key is deliberately not primed here: this page is cacheable, so a key
 * rendered into it belongs to whoever warmed the cache. The island reads it from
 * the cookie instead, which is the same value RegisterFormKeyFromCookie syncs
 * into the session.
 */
class OrdersReturnsForm implements ArgumentInterface
{
    /**
     * @param UrlInterface $url
     */
    public function __construct(
        private readonly UrlInterface $url
    ) {
    }

    /**
     * URL the guest lookup form POSTs to.
     */
    public function getActionUrl(): string
    {
        return $this->url->getUrl('sales/guest/view');
    }
}
