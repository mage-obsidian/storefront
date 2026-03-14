<?php
declare(strict_types=1);

namespace MageObsidian\Storefront\Test\Unit\ViewModel;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Directory\Model\AllowedCountries;
use Magento\Directory\Model\ResourceModel\Country\Collection as CountryCollection;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Directory\Model\ResourceModel\Region\Collection as RegionCollection;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Storefront\ViewModel\DirectoryData;
use PHPUnit\Framework\TestCase;

/**
 * Primes the Vue address form with store-scoped countries/regions and the
 * state-required rules, reusing Magento's directory collections/helper (the same
 * source Luma ships as getRegionJson). Asserts the shape the island consumes and
 * that any failure degrades to empty data. Needs Magento Directory types, so it
 * runs in a Magento root.
 */
class DirectoryDataTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(DirectoryHelper::class)) {
            $this->markTestSkipped('Magento Directory is not available in this runtime.');
        }
    }

    private function region(int $id, string $country, string $code, string $name): object
    {
        $region = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getId', 'getCountryId', 'getCode', 'getName'])
            ->getMock();
        $region->method('getId')->willReturn($id);
        $region->method('getCountryId')->willReturn($country);
        $region->method('getCode')->willReturn($code);
        $region->method('getName')->willReturn($name);

        return $region;
    }

    private function subject(array $overrides = []): DirectoryData
    {
        $countryCollection = $this->createMock(CountryCollection::class);
        $countryCollection->method('loadByStore')->willReturnSelf();
        $countryCollection->method('toOptionArray')->willReturn(
            $overrides['countries'] ?? [
                ['value' => '', 'label' => ' '],
                ['value' => 'US', 'label' => 'United States'],
                ['value' => 'PT', 'label' => 'Portugal'],
            ]
        );
        $countryFactory = $this->createMock(CountryCollectionFactory::class);
        $countryFactory->method('create')->willReturn($countryCollection);

        $regionCollection = $this->createMock(RegionCollection::class);
        $regionCollection->method('addCountryFilter')->willReturnSelf();
        $regionCollection->method('getIterator')->willReturn(new \ArrayIterator(
            $overrides['regions'] ?? [
                $this->region(1, 'US', 'AL', 'Alabama'),
                $this->region(12, 'US', 'CA', 'California'),
            ]
        ));
        $regionFactory = $this->createMock(RegionCollectionFactory::class);
        $regionFactory->method('create')->willReturn($regionCollection);

        $helper = $this->createMock(DirectoryHelper::class);
        $helper->method('getDefaultCountry')->willReturn($overrides['default'] ?? 'US');
        $helper->method('getCountriesWithStatesRequired')->willReturn($overrides['statesRequired'] ?? ['US', 'CA']);
        $helper->method('isShowNonRequiredState')->willReturn($overrides['displayAll'] ?? false);

        $allowed = $this->createMock(AllowedCountries::class);
        $allowed->method('getAllowedCountries')->willReturn($overrides['allowed'] ?? ['US', 'PT']);

        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new DirectoryData($countryFactory, $regionFactory, $helper, $allowed, $storeManager);
    }

    public function testBuildsCountriesRegionsAndRules(): void
    {
        $data = $this->subject()->getData();

        $this->assertSame(
            [['value' => 'US', 'label' => 'United States'], ['value' => 'PT', 'label' => 'Portugal']],
            $data['countries']
        );
        $this->assertArrayHasKey('US', $data['regions']);
        $this->assertSame(['id' => 1, 'code' => 'AL', 'name' => 'Alabama'], $data['regions']['US'][0]);
        $this->assertSame('US', $data['defaultCountry']);
        $this->assertSame(['US', 'CA'], $data['statesRequired']);
        $this->assertFalse($data['displayAllRegions']);
    }

    public function testDropsTheEmptyCountryPlaceholder(): void
    {
        $data = $this->subject()->getData();

        foreach ($data['countries'] as $country) {
            $this->assertNotSame('', $country['value']);
        }
    }

    public function testEncodesPayloadAsJson(): void
    {
        $json = $this->subject()->getDataJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame('US', $decoded['defaultCountry']);
    }

    public function testReturnsEmptyDataWhenNoCountriesAllowed(): void
    {
        $data = $this->subject(['allowed' => []])->getData();

        $this->assertSame([], $data['regions']);
        $this->assertSame('US', $data['defaultCountry']);
    }
}
