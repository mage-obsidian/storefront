<?php
/**
 * This file is part of the MageObsidian - Sales project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\Sales\Model\AccountNavCounter;

use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Sales\Model\Order\Config as OrderConfig;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use MageObsidian\Customer\Api\AccountNavCounterInterface;

/**
 * Badge for the "My Orders" rail entry.
 *
 * Counts the same set the history page lists — statuses visible on the front —
 * so the badge and the list can never disagree. getSize() issues a COUNT, not a
 * load, and the class is injected as a \Proxy, so pages that never draw the rail
 * pay nothing.
 */
class Orders implements AccountNavCounterInterface
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly OrderConfig $orderConfig,
        private readonly CurrentCustomer $currentCustomer
    ) {
    }

    public function getCount(): ?int
    {
        $customerId = (int)$this->currentCustomer->getCustomerId();
        if ($customerId === 0) {
            return null;
        }

        $collection = $this->collectionFactory->create()
            ->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('status', ['in' => $this->orderConfig->getVisibleOnFrontStatuses()]);

        return (int)$collection->getSize();
    }
}
