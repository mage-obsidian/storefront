<?php
/**
 * This file is part of the MageObsidian - Customer project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\Customer\ViewModel;

use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Supplies the address form with clean option data so the Twig can render its own
 * OBSIDIAN-styled selects instead of the block's Luma markup. Country/region lists
 * come straight from the directory collections (store-scoped allowed countries);
 * the region select is rendered server-side for the current country so the form
 * is usable without JS, while address-form-enhancer.ts swaps regions on change.
 */
class AddressForm implements ArgumentInterface
{
    private const XML_PATH_DEFAULT_COUNTRY = 'general/country/default';

    public function __construct(
        private readonly CountryCollectionFactory $countryCollectionFactory,
        private readonly RegionCollectionFactory $regionCollectionFactory,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Allowed countries for the current store, as {value, label} pairs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getCountryOptions(): array
    {
        $options = [];
        $collection = $this->countryCollectionFactory->create()->loadByStore();
        foreach ($collection->toOptionArray(false) as $option) {
            if (($option['value'] ?? '') === '') {
                continue;
            }
            $options[] = ['value' => (string)$option['value'], 'label' => (string)$option['label']];
        }

        return $options;
    }

    /**
     * Regions of a country as {value, label} pairs; empty when the country has none.
     *
     * @param string $countryId
     * @return array<int, array{value: string, label: string}>
     */
    public function getRegionOptions(string $countryId): array
    {
        if ($countryId === '') {
            return [];
        }

        $options = [];
        $collection = $this->regionCollectionFactory->create()->addCountryFilter($countryId);
        foreach ($collection as $region) {
            $options[] = [
                'value' => (string)$region->getId(),
                'label' => (string)$region->getName(),
            ];
        }

        return $options;
    }

    /**
     * The store's configured default country, used to preselect a new address.
     */
    public function getDefaultCountryId(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_DEFAULT_COUNTRY,
            ScopeInterface::SCOPE_STORE
        );
    }
}
