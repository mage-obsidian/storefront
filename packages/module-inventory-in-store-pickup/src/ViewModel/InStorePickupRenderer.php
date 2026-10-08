<?php
/**
 * This file is part of the MageObsidian - InventoryInStorePickup project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\InventoryInStorePickup\ViewModel;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\InventoryInStorePickupApi\Model\SearchRequest\Area\SearchTerm\DelimiterConfig;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Checkout\Api\ShippingRendererInterface;

class InStorePickupRenderer implements ShippingRendererInterface, ArgumentInterface
{
    public const string CARRIER = 'instore';

    public const string COMPONENT = 'MageObsidian_InventoryInStorePickup::shipping/InStorePickup';

    private const int DEFAULT_RADIUS = 200;

    private const int PAGE_SIZE = 10;

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly DelimiterConfig $delimiterConfig,
        private readonly DirectoryHelper $directory
    ) {
    }

    public function getCarrierCodes(): array
    {
        return [self::CARRIER];
    }

    public function getComponent(): string
    {
        return self::COMPONENT;
    }

    public function getConfig(): array
    {
        $store = $this->storeManager->getStore();
        $website = $this->storeManager->getWebsite($store->getWebsiteId());

        return [
            'restBaseUrl' => $store->getBaseUrl() . 'rest/' . $store->getCode() . '/V1/',
            'scopeCode' => (string)$website->getCode(),
            'delimiter' => $this->delimiterConfig->getDelimiter(),
            'radius' => $this->radius(),
            'pageSize' => self::PAGE_SIZE,
            'defaultCountry' => (string)$this->directory->getDefaultCountry(),
            'labels' => $this->labels(),
        ];
    }

    private function radius(): int
    {
        $radius = (int)$this->scopeConfig->getValue('carriers/instore/search_radius', ScopeInterface::SCOPE_STORE);

        return $radius > 0 ? $radius : self::DEFAULT_RADIUS;
    }

    private function labels(): array
    {
        return [
            'delivery' => (string)__('Delivery method'),
            'ship' => (string)__('Ship to my address'),
            'pickup' => (string)__('Pick up in store'),
            'search' => (string)__('Postal code or city'),
            'find' => (string)__('Find pickup locations'),
            'none' => (string)__('No pickup locations found for that search.'),
            'loading' => (string)__('Looking for pickup locations…'),
            'failed' => (string)__('We could not load the pickup locations. Try again.'),
            'choose' => (string)__('Pick up here'),
            'chosen' => (string)__('Selected'),
            'locations' => (string)__('Pickup locations'),
        ];
    }
}
