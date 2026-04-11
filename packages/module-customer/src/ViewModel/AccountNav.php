<?php
/**
 * This file is part of the MageObsidian - Customer project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\Customer\ViewModel;

use Magento\Framework\App\Request\Http;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Account sidebar links, injected via di.xml so domain modules (reviews, wishlist)
 * can contribute an entry without this module knowing about them — the engine
 * suppresses Magento's native Account\Navigation, their usual layout channel.
 */
class AccountNav implements ArgumentInterface
{
    /**
     * @param UrlInterface $url
     * @param Http $request
     * @param array $links Account-nav link defs keyed by id (route/match/label/sortOrder)
     */
    public function __construct(
        private readonly UrlInterface $url,
        private readonly Http $request,
        private readonly array $links = []
    ) {
    }

    /**
     * The sidebar links, sorted and each flagged active for the current page.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLinks(): array
    {
        $current = (string)$this->request->getFullActionName();

        $items = [];
        foreach ($this->links as $id => $link) {
            if (empty($link['route'])) {
                continue;
            }
            $items[] = [
                'id' => (string)$id,
                'url' => $this->url->getUrl($link['route']),
                'active' => str_starts_with($current, (string)($link['match'] ?? '')),
                'label' => (string)($link['label'] ?? $id),
                'sortOrder' => (int)($link['sortOrder'] ?? 0),
            ];
        }
        usort($items, static fn (array $a, array $b): int => $a['sortOrder'] <=> $b['sortOrder']);

        return array_map(
            static fn (array $item): array => [
                'id' => $item['id'],
                'url' => $item['url'],
                'active' => $item['active'],
                'label' => $item['label'],
            ],
            $items
        );
    }

    /**
     * URL of the logout action (the sidebar's Sign Out link).
     */
    public function getLogoutUrl(): string
    {
        return $this->url->getUrl('customer/account/logout');
    }
}
