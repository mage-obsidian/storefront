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
use Magento\Customer\Model\Options;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Exception\NoSuchEntityException;
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
        $metadata = $this->createMock($interface);
        $metadata->method('getAttributeMetadata')->willReturnCallback(
            static function (string $code) use ($attributes) {
                return $attributes[$code] ?? throw new NoSuchEntityException(__('No such attribute.'));
            }
        );

        return $metadata;
    }

    private function viewModel(
        array $customerAttributes = [],
        array $addressAttributes = [],
        array $prefixOptions = [],
        array $suffixOptions = [],
        array $optionalZip = []
    ): FormAttributes {
        $options = $this->createMock(Options::class);
        $options->method('getNamePrefixOptions')->willReturn($prefixOptions === [] ? false : $prefixOptions);
        $options->method('getNameSuffixOptions')->willReturn($suffixOptions === [] ? false : $suffixOptions);

        $directory = $this->createMock(DirectoryHelper::class);
        $directory->method('getCountriesWithOptionalZip')->willReturn($optionalZip);

        return new FormAttributes(
            $this->metadata(CustomerMetadataInterface::class, $customerAttributes),
            $this->metadata(AddressMetadataInterface::class, $addressAttributes),
            $options,
            $directory
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
