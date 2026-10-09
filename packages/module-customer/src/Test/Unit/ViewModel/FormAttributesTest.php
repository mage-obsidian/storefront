<?php
/**
 * This file is part of the MageObsidian - Customer project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Customer\Test\Unit\ViewModel;

use Magento\Customer\Api\AddressMetadataInterface;
use Magento\Customer\Api\CustomerMetadataInterface;
use Magento\Customer\Api\Data\AttributeMetadataInterface;
use Magento\Customer\Api\Data\OptionInterface;
use Magento\Customer\Helper\Address as AddressHelper;
use Magento\Customer\Model\Options;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use MageObsidian\Customer\ViewModel\FormAttributes;
use PHPUnit\Framework\TestCase;

class FormAttributesTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(CustomerMetadataInterface::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    private function attribute(bool $visible, bool $required, int $lines = 0): AttributeMetadataInterface
    {
        $attribute = $this->createMock(AttributeMetadataInterface::class);
        $attribute->method('isVisible')->willReturn($visible);
        $attribute->method('isRequired')->willReturn($required);
        $attribute->method('getMultilineCount')->willReturn($lines);

        return $attribute;
    }

    private function metadata(string $interface, array $attributes): CustomerMetadataInterface|AddressMetadataInterface
    {
        foreach ($attributes as $code => $attribute) {
            $attribute->method('getAttributeCode')->willReturn($code);
        }

        $metadata = $this->createMock($interface);
        $metadata->expects($this->never())->method('getAttributeMetadata');
        $metadata->expects($this->atMost(1))->method('getAllAttributesMetadata')->willReturn(array_values($attributes));

        return $metadata;
    }

    private function viewModel(
        array $customerAttributes = [],
        array $addressAttributes = [],
        array $prefixOptions = [],
        array $suffixOptions = [],
        array $optionalZip = [],
        bool $vatVisibleOnStorefront = true,
        string $dateFormat = 'M/d/y',
        string $locale = 'en_US'
    ): FormAttributes {
        $options = $this->createMock(Options::class);
        $options->method('getNamePrefixOptions')->willReturn($prefixOptions === [] ? false : $prefixOptions);
        $options->method('getNameSuffixOptions')->willReturn($suffixOptions === [] ? false : $suffixOptions);

        $directory = $this->createMock(DirectoryHelper::class);
        $directory->method('getCountriesWithOptionalZip')->willReturn($optionalZip);

        $addressHelper = $this->createMock(AddressHelper::class);
        $addressHelper->method('isVatAttributeVisible')->willReturn($vatVisibleOnStorefront);

        $timezone = $this->createMock(TimezoneInterface::class);
        $timezone->method('getDateFormatWithLongYear')->willReturn($dateFormat);
        $resolver = $this->createMock(ResolverInterface::class);
        $resolver->method('getLocale')->willReturn($locale);

        return new FormAttributes(
            $this->metadata(CustomerMetadataInterface::class, $customerAttributes),
            $this->metadata(AddressMetadataInterface::class, $addressAttributes),
            $options,
            $directory,
            $addressHelper,
            $timezone,
            $resolver
        );
    }

    public function testCustomerFieldReportsVisibleAndRequired(): void
    {
        $viewModel = $this->viewModel(['taxvat' => $this->attribute(true, true)]);

        $this->assertSame(
            ['visible' => true, 'required' => true, 'options' => []],
            $viewModel->customerField('taxvat')
        );
    }

    public function testHiddenCustomerFieldIsNotRequired(): void
    {
        $viewModel = $this->viewModel(['dob' => $this->attribute(false, true)]);

        $this->assertSame(
            ['visible' => false, 'required' => false, 'options' => []],
            $viewModel->customerField('dob')
        );
    }

    public function testUnknownAttributeIsHidden(): void
    {
        $this->assertSame(
            ['visible' => false, 'required' => false, 'options' => []],
            $this->viewModel()->customerField('unknown')
        );
    }

    public function testPrefixOffersTheConfiguredOptions(): void
    {
        $viewModel = $this->viewModel(
            ['prefix' => $this->attribute(true, false)],
            ['prefix' => $this->attribute(true, false)],
            ['' => ' ', 'Mr.' => 'Mr.', 'Mrs.' => 'Mrs.']
        );

        $this->assertSame(['Mr.', 'Mrs.'], $viewModel->customerField('prefix')['options']);
        $this->assertSame(['Mr.', 'Mrs.'], $viewModel->addressField('prefix')['options']);
    }

    public function testSuffixOffersTheConfiguredOptions(): void
    {
        $viewModel = $this->viewModel([], ['suffix' => $this->attribute(true, true)], [], ['Jr.' => 'Jr.']);

        $this->assertSame(['Jr.'], $viewModel->addressField('suffix')['options']);
    }

    public function testAddressFieldReadsTheAddressMetadata(): void
    {
        $viewModel = $this->viewModel([], ['telephone' => $this->attribute(true, false)]);

        $this->assertSame(
            ['visible' => true, 'required' => false, 'options' => []],
            $viewModel->addressField('telephone')
        );
    }

    public function testTheVatNumberStaysHiddenUntilTheStoreAsksForIt(): void
    {
        $viewModel = $this->viewModel([], ['vat_id' => $this->attribute(true, false)], [], [], [], false);

        $this->assertSame(
            ['visible' => false, 'required' => false, 'options' => []],
            $viewModel->addressField('vat_id')
        );
    }

    public function testTheVatNumberShowsWhenTheStoreAsksForIt(): void
    {
        $viewModel = $this->viewModel([], ['vat_id' => $this->attribute(true, false)], [], [], [], true);

        $this->assertTrue($viewModel->addressField('vat_id')['visible']);
    }

    public function testTheStorefrontVisibilitySettingDoesNotTouchOtherFields(): void
    {
        $viewModel = $this->viewModel([], ['company' => $this->attribute(true, false)], [], [], [], false);

        $this->assertTrue($viewModel->addressField('company')['visible']);
    }

    public function testNameOptionsAreNotEscapedAgainBecauseTheCoreAlreadyEscapedThem(): void
    {
        $viewModel = $this->viewModel(
            ['prefix' => $this->attribute(true, false)],
            [],
            ['Mr. &amp; Mrs.' => 'Mr. &amp; Mrs.', 'O&#039;Brien' => 'O&#039;Brien']
        );

        $this->assertSame(['Mr. & Mrs.', "O'Brien"], $viewModel->customerField('prefix')['options']);
    }

    public function testTheBirthDateIsShownInTheFormatTheCoreFilterParses(): void
    {
        $viewModel = $this->viewModel([], [], [], [], [], true, 'dd/MM/y', 'es_ES');

        $this->assertSame('dd/MM/y', $viewModel->dobPattern());
        $this->assertSame('31/12/1990', $viewModel->dobDisplay('1990-12-31 00:00:00'));
    }

    public function testTheBirthDateKeepsTheDayWithTheMonthFirstLocale(): void
    {
        $viewModel = $this->viewModel();

        $this->assertSame('3/4/1985', $viewModel->dobDisplay('1985-03-04'));
    }

    public function testNoBirthDateIsShownAsNothing(): void
    {
        $viewModel = $this->viewModel();

        $this->assertSame('', $viewModel->dobDisplay(null));
        $this->assertSame('', $viewModel->dobDisplay(''));
    }

    public function testAnUnreadableBirthDateIsShownAsItCame(): void
    {
        $this->assertSame('not a date', $this->viewModel()->dobDisplay('not a date'));
    }

    public function testStreetLinesFollowTheMultilineCount(): void
    {
        $this->assertSame(3, $this->viewModel([], ['street' => $this->attribute(true, true, 3)])->streetLines());
    }

    public function testStreetLinesDefaultToTwoWhenTheCountIsNotSet(): void
    {
        $this->assertSame(2, $this->viewModel([], ['street' => $this->attribute(true, true, 0)])->streetLines());
    }

    public function testOptionalZipCountriesComeFromTheDirectory(): void
    {
        $this->assertSame(['IE', 'HK'], $this->viewModel([], [], [], [], ['IE', 'HK'])->optionalZipCountries());
    }

    public function testCheckoutConfigListsEveryAddressFieldAndTheOptionalZipCountries(): void
    {
        $viewModel = $this->viewModel(
            [],
            [
                'telephone' => $this->attribute(true, false),
                'company' => $this->attribute(false, false),
                'street' => $this->attribute(true, true, 3),
            ],
            [],
            [],
            ['IE']
        );

        $config = $viewModel->checkoutConfig();

        $this->assertSame(['visible' => true, 'required' => false, 'options' => []], $config['fields']['telephone']);
        $this->assertSame(['visible' => false, 'required' => false, 'options' => []], $config['fields']['company']);
        $this->assertSame(['visible' => false, 'required' => false, 'options' => []], $config['fields']['fax']);
        $this->assertSame(3, $config['streetLines']);
        $this->assertSame(['IE'], $config['optionalZipCountries']);
    }

    public function testEveryFieldIsDescribedFromASingleReadOfEachMetadata(): void
    {
        $gender = $this->attribute(true, false);
        $gender->method('getOptions')->willReturn([]);
        $viewModel = $this->viewModel(
            ['prefix' => $this->attribute(true, false), 'dob' => $this->attribute(true, false), 'gender' => $gender],
            ['telephone' => $this->attribute(true, false), 'street' => $this->attribute(true, true, 3)]
        );

        $viewModel->customerFields();
        $viewModel->genderOptions();
        $viewModel->checkoutConfig();
        $viewModel->addressField('telephone');

        $this->assertSame(3, $viewModel->streetLines());
    }

    private function option(string $value, string $label): OptionInterface
    {
        $option = $this->createMock(OptionInterface::class);
        $option->method('getValue')->willReturn($value);
        $option->method('getLabel')->willReturn($label);

        return $option;
    }

    public function testGenderOptionsPairTheValueWithItsLabelAndDropTheBlankOne(): void
    {
        $gender = $this->attribute(true, false);
        $gender->method('getOptions')->willReturn([
            $this->option('', ' '),
            $this->option('1', 'Male'),
            $this->option('2', 'Female'),
        ]);

        $this->assertSame(
            [['value' => '1', 'label' => 'Male'], ['value' => '2', 'label' => 'Female']],
            $this->viewModel(['gender' => $gender])->genderOptions()
        );
    }

    public function testGenderOptionsAreEmptyWhenTheAttributeIsUnknown(): void
    {
        $this->assertSame([], $this->viewModel()->genderOptions());
    }
}
