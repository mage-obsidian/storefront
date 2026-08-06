<?php
/**
 * This file is part of the MageObsidian - Customer project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\Customer\Api;

/**
 * Supplies the badge number for one account-nav entry.
 *
 * This is how a domain module puts a count next to its own link without this
 * module knowing it exists: it implements this and names the class in the
 * `counter` key of its AccountNav link definition. Implementations are injected
 * as \Proxy, so nothing is constructed — and no query runs — until the rail is
 * actually rendered.
 */
interface AccountNavCounterInterface
{
    /**
     * The number to show, or null to show none (an empty list is not a zero
     * worth printing on a nav item).
     */
    public function getCount(): ?int;
}
