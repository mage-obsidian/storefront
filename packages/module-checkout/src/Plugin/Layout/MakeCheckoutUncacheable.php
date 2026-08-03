<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Checkout\Plugin\Layout;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\View\Layout;
use MageObsidian\Checkout\Model\Shell\Gate;

/**
 * Keeps the checkout out of the page cache unless it is served as a store-scoped
 * shell.
 *
 * Not a layout handle, because none of the declarative routes work: a
 * `referenceBlock` with `cacheable="false"` is invisible to the
 * `//block[@cacheable="false"]` check, a `<block>` added by a handle never
 * reaches the structure the check also requires, and `isCacheable()` runs against
 * more than one layout per request — the one without the handle is what sets the
 * public headers. Each of those left the bypass page, quote and masked cart id
 * included, in the cache and served to the next visitor.
 *
 * Scoped to the checkout action and one-way: the flag is off by default, so an
 * unscoped version would take the whole storefront out of cache.
 */
class MakeCheckoutUncacheable
{
    private const string CHECKOUT_ACTION = 'checkout_index_index';

    /**
     * @param Gate $gate
     * @param HttpRequest $request
     */
    public function __construct(
        private readonly Gate $gate,
        private readonly HttpRequest $request
    ) {
    }

    /**
     * @param Layout $subject
     * @param bool $result
     * @return bool
     */
    public function afterIsCacheable(Layout $subject, bool $result): bool
    {
        if (!$result || $this->request->getFullActionName() !== self::CHECKOUT_ACTION) {
            return $result;
        }

        return $this->gate->isCacheable();
    }
}
