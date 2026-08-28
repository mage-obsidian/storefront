<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Checkout\Model\Payment;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Api\PaymentMethodListInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

class MethodConfig
{
    public const string CC_FORM = 'ccform';

    private const string AVAILABLE_TYPES = 'availableTypes';

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
            if ($key === '' || $key === self::CC_FORM || !is_array($value)) {
                continue;
            }
            if ($this->methodFor($key, $active) !== null) {
                $config[$key] = $value;
            }
        }

        return $config;
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
