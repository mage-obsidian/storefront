<?php
declare(strict_types=1);

namespace MageObsidian\Customer\Test\Unit\ViewModel;

use Magento\Directory\Model\Region;
use Magento\Directory\Model\ResourceModel\Country\Collection as CountryCollection;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Directory\Model\ResourceModel\Region\Collection as RegionCollection;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use MageObsidian\Customer\ViewModel\AddressForm;
use PHPUnit\Framework\TestCase;

/**
 * The address form data source. We assert the country list drops the empty
 * placeholder, regions map to {value,label}, and the default country is read
 * from store config.
 */
class AddressFormTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(CountryCollectionFactory::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    private function buildViewModel(
        CountryCollectionFactory $countries,
        RegionCollectionFactory $regions,
        ScopeConfigInterface $config
    ): AddressForm {
        return new AddressForm($countries, $regions, $config);
    }

    public function testCountryOptionsDropTheEmptyPlaceholder(): void
    {
        $collection = $this->createMock(CountryCollection::class);
        $collection->method('loadByStore')->willReturnSelf();
        $collection->method('toOptionArray')->willReturn([
            ['value' => '', 'label' => ' '],
            ['value' => 'US', 'label' => 'United States'],
            ['value' => 'ES', 'label' => 'Spain'],
        ]);

        $factory = $this->createMock(CountryCollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $viewModel = $this->buildViewModel(
            $factory,
            $this->createMock(RegionCollectionFactory::class),
            $this->createMock(ScopeConfigInterface::class)
        );

        $this->assertSame(
            [
                ['value' => 'US', 'label' => 'United States'],
                ['value' => 'ES', 'label' => 'Spain'],
            ],
            $viewModel->getCountryOptions()
        );
    }

    public function testRegionOptionsMapIdAndName(): void
    {
        $alabama = $this->createMock(Region::class);
        $alabama->method('getId')->willReturn(1);
        $alabama->method('getName')->willReturn('Alabama');

        $collection = $this->createMock(RegionCollection::class);
        $collection->method('addCountryFilter')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$alabama]));

        $factory = $this->createMock(RegionCollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $viewModel = $this->buildViewModel(
            $this->createMock(CountryCollectionFactory::class),
            $factory,
            $this->createMock(ScopeConfigInterface::class)
        );

        $this->assertSame(
            [['value' => '1', 'label' => 'Alabama']],
            $viewModel->getRegionOptions('US')
        );
    }

    public function testRegionOptionsAreEmptyForBlankCountry(): void
    {
        $viewModel = $this->buildViewModel(
            $this->createMock(CountryCollectionFactory::class),
            $this->createMock(RegionCollectionFactory::class),
            $this->createMock(ScopeConfigInterface::class)
        );

        $this->assertSame([], $viewModel->getRegionOptions(''));
    }

    public function testDefaultCountryComesFromStoreConfig(): void
    {
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn('US');

        $viewModel = $this->buildViewModel(
            $this->createMock(CountryCollectionFactory::class),
            $this->createMock(RegionCollectionFactory::class),
            $config
        );

        $this->assertSame('US', $viewModel->getDefaultCountryId());
    }
}
