<?php
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Checkout\ViewModel;

use InvalidArgumentException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use MageObsidian\Checkout\Api\ShippingRendererInterface;

class ShippingRenderer implements ShippingRendererInterface, ArgumentInterface
{
    private const string QUALIFIED = '/^[A-Za-z0-9]+_[A-Za-z0-9]+::[A-Za-z0-9][A-Za-z0-9\/_.-]*$/';

    public function __construct(
        private readonly array $carriers = [],
        private readonly string $component = '',
        private readonly array $config = []
    ) {
    }

    public function getCarrierCodes(): array
    {
        $codes = array_values(array_unique(array_filter(
            array_map(static fn (mixed $code): string => trim((string)$code), $this->carriers),
            static fn (string $code): bool => $code !== ''
        )));

        if ($codes === []) {
            throw new InvalidArgumentException('A shipping renderer has to name at least one carrier code.');
        }

        return $codes;
    }

    public function getComponent(): string
    {
        $component = trim($this->component);

        if (!preg_match(self::QUALIFIED, $component) || str_contains($component, '..')) {
            throw new InvalidArgumentException(sprintf(
                'A shipping renderer component has to be a qualified "Vendor_Module::path" reference, so a theme '
                . 'override stays reachable; got "%s".',
                $this->component
            ));
        }

        return $component;
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}
