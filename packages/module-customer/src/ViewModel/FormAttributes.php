<?php
/**
 * This file is part of the MageObsidian - Customer project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace MageObsidian\Customer\ViewModel;

use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use Magento\Customer\Api\AddressMetadataInterface;
use Magento\Customer\Api\CustomerMetadataInterface;
use Magento\Customer\Helper\Address as AddressHelper;
use Magento\Customer\Model\Options;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Throwable;

class FormAttributes implements ArgumentInterface
{
    private const ADDRESS_FIELDS = ['prefix', 'middlename', 'suffix', 'company', 'telephone', 'fax', 'vat_id'];
    private const CUSTOMER_FIELDS = ['prefix', 'middlename', 'suffix', 'dob', 'gender', 'taxvat'];
    private const DEFAULT_STREET_LINES = 2;

    public function __construct(
        private readonly CustomerMetadataInterface $customerMetadata,
        private readonly AddressMetadataInterface $addressMetadata,
        private readonly Options $options,
        private readonly DirectoryHelper $directory,
        private readonly AddressHelper $addressHelper,
        private readonly TimezoneInterface $timezone,
        private readonly ResolverInterface $localeResolver
    ) {
    }

    public function customerField(string $code): array
    {
        return $this->describe($this->customerMetadata, $code);
    }

    public function addressField(string $code): array
    {
        if ($code === 'vat_id' && !$this->addressHelper->isVatAttributeVisible()) {
            return ['visible' => false, 'required' => false, 'options' => []];
        }

        return $this->describe($this->addressMetadata, $code);
    }

    public function dobPattern(): string
    {
        return $this->timezone->getDateFormatWithLongYear();
    }

    public function dobDisplay(?string $stored): string
    {
        $stored = trim((string)$stored);
        if ($stored === '') {
            return '';
        }

        try {
            $date = new DateTimeImmutable($stored, new DateTimeZone('UTC'));
            $formatter = new IntlDateFormatter(
                $this->localeResolver->getLocale(),
                IntlDateFormatter::NONE,
                IntlDateFormatter::NONE,
                'UTC',
                IntlDateFormatter::GREGORIAN,
                $this->dobPattern()
            );
            $formatted = $formatter->format($date);
        } catch (Throwable) {
            return $stored;
        }

        return $formatted === false ? $stored : $formatted;
    }

    public function streetLines(): int
    {
        try {
            $lines = $this->addressMetadata->getAttributeMetadata('street')->getMultilineCount();
        } catch (NoSuchEntityException) {
            return self::DEFAULT_STREET_LINES;
        }

        return $lines > 0 ? $lines : self::DEFAULT_STREET_LINES;
    }

    public function optionalZipCountries(): array
    {
        return array_values((array)$this->directory->getCountriesWithOptionalZip());
    }

    public function checkoutConfig(): array
    {
        $fields = [];
        foreach (self::ADDRESS_FIELDS as $code) {
            $fields[$code] = $this->addressField($code);
        }

        return [
            'fields' => $fields,
            'streetLines' => $this->streetLines(),
            'optionalZipCountries' => $this->optionalZipCountries(),
        ];
    }

    public function customerFields(): array
    {
        $fields = [];
        foreach (self::CUSTOMER_FIELDS as $code) {
            $fields[$code] = $this->customerField($code);
        }

        return $fields;
    }

    public function genderOptions(): array
    {
        try {
            $options = $this->customerMetadata->getAttributeMetadata('gender')->getOptions();
        } catch (NoSuchEntityException) {
            return [];
        }

        $pairs = [];
        foreach ($options as $option) {
            if (trim((string)$option->getValue()) === '') {
                continue;
            }
            $pairs[] = ['value' => (string)$option->getValue(), 'label' => (string)$option->getLabel()];
        }

        return $pairs;
    }

    private function describe(CustomerMetadataInterface|AddressMetadataInterface $metadata, string $code): array
    {
        try {
            $attribute = $metadata->getAttributeMetadata($code);
        } catch (NoSuchEntityException) {
            return ['visible' => false, 'required' => false, 'options' => []];
        }

        $visible = (bool)$attribute->isVisible();

        return [
            'visible' => $visible,
            'required' => $visible && (bool)$attribute->isRequired(),
            'options' => $visible ? $this->optionsFor($code) : [],
        ];
    }

    private function optionsFor(string $code): array
    {
        $configured = match ($code) {
            'prefix' => $this->options->getNamePrefixOptions(),
            'suffix' => $this->options->getNameSuffixOptions(),
            default => false,
        };

        if (!is_array($configured)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn($label): string => trim(html_entity_decode((string)$label, ENT_QUOTES | ENT_HTML5)), $configured),
            static fn(string $label): bool => $label !== ''
        ));
    }
}
