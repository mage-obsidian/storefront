<?php
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Checkout\Model\Payment;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Api\PaymentMethodListInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

class MethodConfig
{
    public const string CC_FORM = 'ccform';

    private const string AVAILABLE_TYPES = 'availableTypes';

    private const string INSTRUCTIONS = 'instructions';

    private const array PLAIN_TEXT_FIELDS = ['mailingAddress', 'payableTo'];

    public function __construct(
        private readonly ConfigProviderInterface $configProviders,
        private readonly PaymentMethodListInterface $methodList,
        private readonly StoreManagerInterface $storeManager,
        private readonly array $keyAliases = []
    ) {
    }

    public function getConfig(): array
    {
        try {
            $active = $this->activeMethodCodes();
            if ($active === []) {
                return [];
            }

            $payment = $this->paymentSubtree();
            $config = $this->filter($payment, $active);
            $ccForm = $this->ccForm($payment, $active);
            if ($ccForm !== null) {
                $config[self::CC_FORM] = $ccForm;
            }

            return $config;
        } catch (Throwable) {
            return [];
        }
    }

    private function activeMethodCodes(): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $codes = [];

        foreach ($this->methodList->getActiveList($storeId) as $method) {
            $code = (string)$method->getCode();
            if ($code !== '') {
                $codes[$code] = true;
            }
        }

        return $codes;
    }

    private function paymentSubtree(): array
    {
        $payment = $this->configProviders->getConfig()['payment'] ?? [];

        return is_array($payment) ? $payment : [];
    }

    private function filter(array $payment, array $active): array
    {
        $config = [];

        foreach ($payment as $key => $value) {
            $key = (string)$key;
            if ($key === '' || $key === self::CC_FORM || $key === self::INSTRUCTIONS || !is_array($value)) {
                continue;
            }
            if ($this->methodFor($key, $active) !== null) {
                $config[$key] = $this->plainTextFields($value);
            }
        }

        return $this->withPurchaseOrderLabels($this->withInstructions($config, $payment, $active), $active);
    }

    private function withPurchaseOrderLabels(array $config, array $active): array
    {
        if (isset($active['purchaseorder'])) {
            $config['purchaseorder']['labels'] = [
                'poNumber' => (string)__('Purchase Order Number'),
                'required' => (string)__('Enter the purchase order number.'),
            ];
        }

        return $config;
    }

    private function withInstructions(array $config, array $payment, array $active): array
    {
        $instructions = $payment[self::INSTRUCTIONS] ?? null;
        if (!is_array($instructions)) {
            return $config;
        }

        foreach ($instructions as $code => $text) {
            $code = (string)$code;
            $plain = $this->plainText($text);
            if (isset($active[$code]) && $plain !== '') {
                $config[$code][self::INSTRUCTIONS] = $plain;
            }
        }

        return $config;
    }

    private function plainTextFields(array $value): array
    {
        $converted = false;
        foreach (self::PLAIN_TEXT_FIELDS as $field) {
            if (isset($value[$field]) && is_string($value[$field])) {
                $value[$field] = $this->plainText($value[$field]);
                $converted = true;
            }
        }

        if ($converted) {
            $value['labels'] = [
                'payableTo' => (string)__('Make check payable to'),
                'mailingAddress' => (string)__('Send check to'),
            ];
        }

        return $value;
    }

    private function plainText(mixed $html): string
    {
        $text = (string)preg_replace('#<br\s*/?>#i', "\n", (string)$html);

        return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function ccForm(array $payment, array $active): ?array
    {
        $ccForm = $payment[self::CC_FORM] ?? null;
        if (!is_array($ccForm)) {
            return null;
        }

        $cardMethods = array_keys((array)($ccForm[self::AVAILABLE_TYPES] ?? []));
        $wanted = array_values(array_filter($cardMethods, static fn (string $code): bool => isset($active[$code])));
        if ($wanted === []) {
            return null;
        }

        $narrowed = [];
        foreach ($ccForm as $key => $value) {
            $narrowed[$key] = $this->isMethodKeyed($value, $cardMethods)
                ? array_intersect_key($value, array_flip($wanted))
                : $value;
        }

        return $narrowed;
    }

    private function isMethodKeyed(mixed $value, array $cardMethods): bool
    {
        return is_array($value) && $value !== [] && array_diff(array_keys($value), $cardMethods) === [];
    }

    private function methodFor(string $key, array $active): ?string
    {
        $aliased = $this->keyAliases[$key] ?? $key;

        foreach ([$aliased, $this->underscored($aliased)] as $candidate) {
            if (isset($active[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    private function underscored(string $key): string
    {
        return strtolower((string)preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $key));
    }
}
