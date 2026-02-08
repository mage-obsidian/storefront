<?php
/**
 * This file is part of the MageObsidian - Downloadable project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\Downloadable\ViewModel;

use Magento\Downloadable\Model\Link;
use Magento\Downloadable\Model\Sales\Order\Link\Purchased;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Throwable;

/**
 * Purchased-link titles for a downloadable line in an order/invoice document.
 *
 * Wired as an optional `downloadable_links` argument on the MageObsidian Sales
 * host blocks, so the shared item-options partial can list the links a customer
 * bought without Magento_Sales depending on Magento_Downloadable. Reuses the core
 * Sales\Order\Link\Purchased resolver (it returns an empty set for any non
 * downloadable item, so the partial naturally renders nothing for those).
 */
class OrderItemLinks implements ArgumentInterface
{
    /**
     * @param Purchased $purchasedLink
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly Purchased $purchasedLink,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Purchased link titles for an order/invoice item, empty for non-downloadables.
     *
     * @param DataObject $item
     * @return array<int, string>
     */
    public function getLinks(DataObject $item): array
    {
        try {
            $purchased = $this->purchasedLink->getLink($item);
            $titles = [];
            foreach ($purchased->getPurchasedItems() as $linkItem) {
                $title = trim((string)$linkItem->getLinkTitle());
                if ($title !== '') {
                    $titles[] = $title;
                }
            }

            return $titles;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Section label shown before the links (store "Links" title, default "Links").
     */
    public function getTitle(): string
    {
        $title = $this->scopeConfig->getValue(Link::XML_PATH_LINKS_TITLE, ScopeInterface::SCOPE_STORE);

        return $title !== null && $title !== '' ? (string)$title : 'Links';
    }
}
