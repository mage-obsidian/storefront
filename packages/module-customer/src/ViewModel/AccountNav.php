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
 * The account sidebar links. Magento's native Account\Navigation sources its
 * links from other modules' layout contributions, which the engine suppresses —
 * so this rebuilds the canonical set from native routes and flags the active one
 * by the current full action name.
 */
class AccountNav implements ArgumentInterface
{
    /** @var array<int, array{id: string, route: string, match: string}> */
    private const LINKS = [
        ['id' => 'dashboard', 'route' => 'customer/account', 'match' => 'customer_account_index'],
        ['id' => 'orders', 'route' => 'sales/order/history', 'match' => 'sales_order'],
        ['id' => 'address', 'route' => 'customer/address', 'match' => 'customer_address'],
        ['id' => 'edit', 'route' => 'customer/account/edit', 'match' => 'customer_account_edit'],
        ['id' => 'newsletter', 'route' => 'newsletter/manage', 'match' => 'newsletter_manage'],
    ];

    /**
     * @param UrlInterface $url
     * @param Http $request
     */
    public function __construct(
        private readonly UrlInterface $url,
        private readonly Http $request
    ) {
    }

    /**
     * The sidebar links, each flagged active when it matches the current page.
     *
     * @return array<int, array{id: string, url: string, active: bool}>
     */
    public function getLinks(): array
    {
        $current = (string)$this->request->getFullActionName();

        $links = [];
        foreach (self::LINKS as $link) {
            $links[] = [
                'id' => $link['id'],
                'url' => $this->url->getUrl($link['route']),
                'active' => str_starts_with($current, $link['match']),
            ];
        }

        return $links;
    }

    /**
     * URL of the logout action (the sidebar's Sign Out link).
     */
    public function getLogoutUrl(): string
    {
        return $this->url->getUrl('customer/account/logout');
    }
}
