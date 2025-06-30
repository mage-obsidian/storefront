<?php
/**
 * This file is part of the MageObsidian - Customer project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\Customer\ViewModel;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Account URLs for the header account island. The island decides what to show
 * (guest "Sign In" vs the logged-in disclosure) from the client-side `customer`
 * customer-data section — never from server state baked into FPC-cached HTML.
 * This ViewModel only resolves the store-aware URLs the menu links to; the
 * (translatable) labels stay in Twig.
 */
class AccountState implements ArgumentInterface
{
    /**
     * @param UrlInterface $url
     */
    public function __construct(
        private readonly UrlInterface $url
    ) {
    }

    /**
     * URL of the account dashboard (the no-JS header link also points here).
     */
    public function getAccountUrl(): string
    {
        return $this->url->getUrl('customer/account');
    }

    /**
     * URL of the sign-in page.
     */
    public function getLoginUrl(): string
    {
        return $this->url->getUrl('customer/account/login');
    }

    /**
     * URL of the create-account page.
     */
    public function getRegisterUrl(): string
    {
        return $this->url->getUrl('customer/account/create');
    }

    /**
     * URL of the customer's order history.
     */
    public function getOrdersUrl(): string
    {
        return $this->url->getUrl('sales/order/history');
    }

    /**
     * URL of the customer's address book.
     */
    public function getAddressesUrl(): string
    {
        return $this->url->getUrl('customer/address');
    }

    /**
     * URL of the logout action.
     */
    public function getLogoutUrl(): string
    {
        return $this->url->getUrl('customer/account/logout');
    }
}
