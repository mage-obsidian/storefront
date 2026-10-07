<?php
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Checkout\ViewModel;

use Magento\Framework\DataObject;
use Magento\Framework\Pricing\Render;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Tax\Model\Config as TaxConfig;
use Magento\Weee\Helper\Data as WeeeHelper;

class TaxDisplay implements ArgumentInterface
{
    public const string INCL = 'incl';
    public const string EXCL = 'excl';
    public const string BOTH = 'both';

    private const array FIXED_TAX_MODES = [
        0 => 'incl',
        1 => 'incl_desc',
        2 => 'excl_desc_incl',
        3 => 'excl',
    ];

    public function __construct(
        private readonly TaxConfig $taxConfig,
        private readonly WeeeHelper $weeeHelper
    ) {
    }

    public function priceMode(): string
    {
        return $this->mode(
            $this->taxConfig->displayCartPricesBoth(),
            $this->taxConfig->displayCartPricesInclTax()
        );
    }

    public function subtotalMode(): string
    {
        return $this->mode(
            $this->taxConfig->displayCartSubtotalBoth(),
            $this->taxConfig->displayCartSubtotalInclTax()
        );
    }

    public function shippingMode(): string
    {
        return $this->mode(
            $this->taxConfig->displayCartShippingBoth(),
            $this->taxConfig->displayCartShippingInclTax()
        );
    }

    public function isFullSummary(): bool
    {
        return (bool)$this->taxConfig->displayCartFullSummary();
    }

    public function showsZeroTax(): bool
    {
        return (bool)$this->taxConfig->displayCartZeroTax();
    }

    public function fixedTaxMode(): string
    {
        if (!$this->weeeHelper->isEnabled()) {
            return 'off';
        }

        return self::FIXED_TAX_MODES[(int)$this->weeeHelper->typeOfDisplay(null, Render::ZONE_SALES)] ?? 'excl';
    }

    public function includesFixedTaxInPrice(): bool
    {
        return in_array($this->fixedTaxMode(), ['incl', 'incl_desc'], true);
    }

    /**
     * @return array{price: string, subtotal: string, shipping: string, fullSummary: bool, zeroTax: bool, fixedTax: string, fixedTaxInSubtotal: bool}
     */
    public function getConfig(): array
    {
        return [
            'price' => $this->priceMode(),
            'subtotal' => $this->subtotalMode(),
            'shipping' => $this->shippingMode(),
            'fullSummary' => $this->isFullSummary(),
            'zeroTax' => $this->showsZeroTax(),
            'fixedTax' => $this->fixedTaxMode(),
            'fixedTaxInSubtotal' => (bool)$this->weeeHelper->includeInSubtotal(),
        ];
    }

    /**
     * @return array{incl: float|null, excl: float|null}
     */
    public function pick(string $mode, float $incl, float $excl): array
    {
        return [
            'incl' => $mode === self::EXCL ? null : $incl,
            'excl' => $mode === self::INCL ? null : $excl,
        ];
    }

    /**
     * @return list<array{label: string, amount: float, amountInclTax: float}>
     */
    public function itemFixedTaxes(QuoteItem $item): array
    {
        if (!$this->weeeHelper->isEnabled()) {
            return [];
        }

        return array_values(array_map(
            static fn (array $applied): array => [
                'label' => (string)($applied['title'] ?? ''),
                'amount' => (float)($applied['row_amount'] ?? 0),
                'amountInclTax' => (float)($applied['row_amount_incl_tax'] ?? $applied['row_amount'] ?? 0),
            ],
            array_filter((array)$this->weeeHelper->getApplied($item), 'is_array')
        ));
    }

    /**
     * @param iterable<QuoteItem> $items
     * @return list<array{label: string, amount: float}>
     */
    public function fixedTaxTotals(iterable $items): array
    {
        $totals = [];
        foreach ($items as $item) {
            foreach ($this->itemFixedTaxes($item) as $fixedTax) {
                $totals[$fixedTax['label']] = ($totals[$fixedTax['label']] ?? 0.0) + $fixedTax['amount'];
            }
        }

        return array_values(array_map(
            static fn (string $label, float $amount): array => ['label' => $label, 'amount' => $amount],
            array_keys($totals),
            $totals
        ));
    }

    /**
     * @return array{excl: float, incl: float}
     */
    public function itemFixedTaxAmounts(QuoteItem $item): array
    {
        if (!$this->weeeHelper->isEnabled()) {
            return ['excl' => 0.0, 'incl' => 0.0];
        }

        return [
            'excl' => (float)$this->weeeHelper->getWeeeTaxAppliedRowAmount($item),
            'incl' => (float)$this->weeeHelper->getRowWeeeTaxInclTax($item),
        ];
    }

    /**
     * @param iterable<DataObject> $totals
     * @param list<array{label: string, amount: float}> $fixedTaxes
     * @return list<array{code: string, label: string, amount: float, detail: bool}>
     */
    public function totalRows(iterable $totals, string $shippingDescription, array $fixedTaxes): array
    {
        $rows = [];
        $fixedTaxRows = $this->fixedTaxRows($fixedTaxes);
        $fixedTaxPlaced = false;

        foreach ($totals as $total) {
            $code = (string)$total->getData('code');
            if (!$fixedTaxPlaced && in_array($code, ['tax', 'grand_total'], true)) {
                array_push($rows, ...$fixedTaxRows);
                $fixedTaxPlaced = true;
            }
            array_push($rows, ...$this->rowsFor($code, $total, $shippingDescription, $fixedTaxes));
        }

        if (!$fixedTaxPlaced) {
            array_push($rows, ...$fixedTaxRows);
        }

        return $rows;
    }

    private function mode(bool $both, bool $incl): string
    {
        if ($both) {
            return self::BOTH;
        }

        return $incl ? self::INCL : self::EXCL;
    }

    /**
     * @param list<array{label: string, amount: float}> $fixedTaxes
     * @return list<array{code: string, label: string, amount: float, detail: bool}>
     */
    private function rowsFor(string $code, DataObject $total, string $shippingDescription, array $fixedTaxes): array
    {
        $title = (string)$total->getData('title');
        $value = $total->getData('value');

        return match ($code) {
            'subtotal' => $this->subtotalRows($total, $title, $fixedTaxes),
            'shipping' => $this->shippingRows($total, $title, $shippingDescription),
            'tax' => $this->taxRows($total, $title),
            'weee', 'weee_tax' => [],
            default => $value === null ? [] : [$this->row($code, $title, (float)$value)],
        };
    }

    /**
     * @param list<array{label: string, amount: float}> $fixedTaxes
     * @return list<array{code: string, label: string, amount: float, detail: bool}>
     */
    private function subtotalRows(DataObject $total, string $title, array $fixedTaxes): array
    {
        $value = (float)$total->getData('value');
        $incl = (float)($total->getData('value_incl_tax') ?? $value);
        $excl = (float)($total->getData('value_excl_tax') ?? $value);
        $mode = $this->subtotalMode();

        if ($mode === self::BOTH) {
            return [
                $this->row('subtotal_excl_tax', (string)__('Subtotal (Excl. Tax)'), $excl),
                $this->row('subtotal_incl_tax', (string)__('Subtotal (Incl. Tax)'), $incl),
            ];
        }

        if ($mode === self::INCL) {
            return [$this->row('subtotal', $title, $incl)];
        }

        $amount = $excl;
        if ($this->fixedTaxMode() === 'incl' && !$this->weeeHelper->includeInSubtotal()) {
            $amount += array_sum(array_column($fixedTaxes, 'amount'));
        }

        return [$this->row('subtotal', $title, $amount)];
    }

    /**
     * @return list<array{code: string, label: string, amount: float, detail: bool}>
     */
    private function shippingRows(DataObject $total, string $title, string $shippingDescription): array
    {
        $excl = (float)$total->getData('value');
        $incl = (float)($total->getData('shipping_incl_tax') ?? $excl);
        $mode = $this->shippingMode();

        return match ($mode) {
            self::BOTH => [
                $this->row('shipping_excl_tax', (string)__('Shipping Excl. Tax (%1)', $shippingDescription), $excl),
                $this->row('shipping_incl_tax', (string)__('Shipping Incl. Tax (%1)', $shippingDescription), $incl),
            ],
            self::INCL => [$this->row('shipping', $title, $incl)],
            default => [$this->row('shipping', $title, $excl)],
        };
    }

    /**
     * @return list<array{code: string, label: string, amount: float, detail: bool}>
     */
    private function taxRows(DataObject $total, string $title): array
    {
        $amount = (float)$total->getData('value');
        if ($amount === 0.0 && !$this->showsZeroTax()) {
            return [];
        }

        $rows = [$this->row('tax', $title, $amount)];
        if ($amount === 0.0 || !$this->isFullSummary()) {
            return $rows;
        }

        foreach ((array)$total->getData('full_info') as $info) {
            array_push($rows, ...$this->taxDetailRows((array)$info));
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $info
     * @return list<array{code: string, label: string, amount: float, detail: bool}>
     */
    private function taxDetailRows(array $info): array
    {
        if (!empty($info['hidden'])) {
            return [];
        }

        $rates = array_values((array)($info['rates'] ?? []));
        $amount = (float)($info['amount'] ?? 0);
        $percentSum = array_sum(array_map(static fn (array $rate): float => (float)($rate['percent'] ?? 0), $rates));
        $rows = [];
        $remaining = $amount;

        foreach ($rates as $index => $rate) {
            $isLast = $index === count($rates) - 1;
            $share = $isLast || $percentSum <= 0
                ? $remaining
                : round($amount * (float)($rate['percent'] ?? 0) / $percentSum, 2);
            $remaining = round($remaining - $share, 2);
            $rows[] = $this->row('tax_detail', $this->rateLabel($rate), $share, true);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $rate
     */
    private function rateLabel(array $rate): string
    {
        $title = (string)($rate['title'] ?? '');
        $percent = $rate['percent'] ?? null;

        return $percent === null ? $title : sprintf('%s (%s%%)', $title, (float)$percent);
    }

    /**
     * @param list<array{label: string, amount: float}> $fixedTaxes
     * @return list<array{code: string, label: string, amount: float, detail: bool}>
     */
    private function fixedTaxRows(array $fixedTaxes): array
    {
        $mode = $this->fixedTaxMode();
        if (
            $fixedTaxes === []
            || $this->subtotalMode() !== self::EXCL
            || $this->weeeHelper->includeInSubtotal()
            || in_array($mode, ['off', 'incl'], true)
        ) {
            return [];
        }

        if ($mode === 'excl') {
            return [$this->row('fixed_tax', (string)__('Fixed Product Tax'), array_sum(array_column($fixedTaxes, 'amount')))];
        }

        return array_map(
            fn (array $fixedTax): array => $this->row('fixed_tax', $fixedTax['label'], $fixedTax['amount']),
            $fixedTaxes
        );
    }

    /**
     * @return array{code: string, label: string, amount: float, detail: bool}
     */
    private function row(string $code, string $label, float $amount, bool $detail = false): array
    {
        return ['code' => $code, 'label' => $label, 'amount' => $amount, 'detail' => $detail];
    }
}
