<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\ViewModel;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Directory\Model\AllowedCountries;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

/**
 * Store-scoped country/region data for the Vue address form.
 *
 * This is the foundation's directory provider (the Magento_UI counterpart of the
 * native directory blocks): it primes the allowed countries, the regions grouped
 * by country, the default country and the state-required rules so any address
 * island (checkout shipping/billing today, the customer address book later) can
 * render reactive country→region selects without a REST round-trip. It reuses
 * Magento's own directory collections/helper — the same source Luma ships to the
 * browser as `getRegionJson`. Any failure degrades to empty data so the page
 * still renders.
 */
class DirectoryData implements ArgumentInterface
{
    /**
     * Memoised payload.
     *
     * @var array<string, mixed>|null
     */
    private ?array $data = null;

    /**
     * @param CountryCollectionFactory $countryCollectionFactory
     * @param RegionCollectionFactory $regionCollectionFactory
     * @param DirectoryHelper $directoryHelper
     * @param AllowedCountries $allowedCountries
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly CountryCollectionFactory $countryCollectionFactory,
        private readonly RegionCollectionFactory $regionCollectionFactory,
        private readonly DirectoryHelper $directoryHelper,
        private readonly AllowedCountries $allowedCountries,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * The full directory payload the address island mounts with (memoised).
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        try {
            $this->data = $this->build();
        } catch (Throwable) {
            $this->data = $this->emptyData();
        }

        return $this->data;
    }

    /**
     * The directory payload as a JSON string for the island props.
     *
     * @return string
     */
    public function getDataJson(): string
    {
        return (string)json_encode($this->getData());
    }

    /**
     * Assemble the payload from the store-scoped directory collections.
     *
     * @return array<string, mixed>
     */
    private function build(): array
    {
        $allowedCodes = array_values(
            $this->allowedCountries->getAllowedCountries(ScopeInterface::SCOPE_STORE)
        );

        return [
            'countries' => $this->countries(),
            'regions' => $this->regions($allowedCodes),
            'defaultCountry' => (string)$this->directoryHelper->getDefaultCountry(),
            'statesRequired' => array_values($this->directoryHelper->getCountriesWithStatesRequired()),
            'displayAllRegions' => (bool)$this->directoryHelper->isShowNonRequiredState(),
        ];
    }

    /**
     * Allowed countries as `{value, label}` options, store-scoped.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function countries(): array
    {
        $collection = $this->countryCollectionFactory->create()
            ->loadByStore((int)$this->storeManager->getStore()->getId());

        $countries = [];
        foreach ($collection->toOptionArray(false) as $option) {
            $value = (string)($option['value'] ?? '');
            if ($value === '') {
                continue;
            }
            $countries[] = ['value' => $value, 'label' => (string)($option['label'] ?? $value)];
        }

        return $countries;
    }

    /**
     * Regions grouped by country code, limited to the allowed countries.
     *
     * @param string[] $allowedCodes
     * @return array<string, array<int, array{id: int, code: string, name: string}>>
     */
    private function regions(array $allowedCodes): array
    {
        if ($allowedCodes === []) {
            return [];
        }

        $collection = $this->regionCollectionFactory->create()->addCountryFilter($allowedCodes);

        $regions = [];
        foreach ($collection as $region) {
            $countryId = (string)$region->getCountryId();
            $regions[$countryId][] = [
                'id' => (int)$region->getId(),
                'code' => (string)$region->getCode(),
                'name' => (string)$region->getName(),
            ];
        }

        return $regions;
    }

    /**
     * Safe fallback (renders an address form with a free-text region only).
     *
     * @return array<string, mixed>
     */
    private function emptyData(): array
    {
        return [
            'countries' => [],
            'regions' => [],
            'defaultCountry' => '',
            'statesRequired' => [],
            'displayAllRegions' => false,
        ];
    }
}
