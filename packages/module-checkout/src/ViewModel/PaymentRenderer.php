<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Checkout\ViewModel;

use InvalidArgumentException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use MageObsidian\Checkout\Api\PaymentRendererInterface;

class PaymentRenderer implements PaymentRendererInterface, ArgumentInterface
{
    private const string QUALIFIED = '/^[A-Za-z0-9]+_[A-Za-z0-9]+::[A-Za-z0-9][A-Za-z0-9\/_.-]*$/';

    public function __construct(
        private readonly array $methods = [],
        private readonly string $component = ''
    ) {
    }

    public function getMethodCodes(): array
    {
        return $this->normaliseCodes($this->methods);
    }

    protected function normaliseCodes(array $methods): array
    {
        $codes = array_values(array_unique(array_filter(
            array_map(static fn (mixed $code): string => trim((string)$code), $methods),
            static fn (string $code): bool => $code !== ''
        )));

        if ($codes === []) {
            throw new InvalidArgumentException('A payment renderer has to name at least one payment method code.');
        }

        return $codes;
    }

    public function getComponent(): string
    {
        $component = trim($this->component);

        if (!preg_match(self::QUALIFIED, $component) || str_contains($component, '..')) {
            throw new InvalidArgumentException(sprintf(
                'A payment renderer component has to be a qualified "Vendor_Module::path" reference, so a theme '
                . 'override stays reachable; got "%s".',
                $this->component
            ));
        }

        return $component;
    }
}
