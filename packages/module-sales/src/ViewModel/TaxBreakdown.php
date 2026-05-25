<?php
/**
 * This file is part of the MageObsidian - Sales project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\Sales\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Store\Model\ScopeInterface;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Throwable;

/**
 * Per-rate tax detail for the order/invoice/credit-memo totals tables.
 *
 * MageObsidian renders the core totals block directly, so the native tax total
 * renderer (which expands into one line per rate when the store enables the full
 * tax summary) never runs. This restores it: getRates returns the calculated
 * per-rate amounts and isFullSummary mirrors the store's
 * tax/sales_display/full_summary flag the templates branch on.
 */
class TaxBreakdown implements ArgumentInterface
{
    /**
     * Store config flag controlling whether sales documents expand the tax detail.
     */
    private const XML_PATH_FULL_SUMMARY = 'tax/sales_display/full_summary';

    /**
     * @param TaxHelper $taxHelper
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly TaxHelper $taxHelper,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Whether the store shows the expanded per-rate tax summary on documents.
     */
    public function isFullSummary(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_FULL_SUMMARY, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Calculated tax rows for a document ([{title, percent, amount}]).
     *
     * Accepts an order, invoice or credit memo (the tax helper resolves each).
     * Empty when the document carries no tax.
     *
     * @param DataObject $source
     * @return array<int, array{title: string, percent: float, amount: float}>
     */
    public function getRates(DataObject $source): array
    {
        try {
            $rates = $this->taxHelper->getCalculatedTaxes($source);
        } catch (Throwable) {
            return [];
        }

        $result = [];
        foreach ($rates as $rate) {
            $result[] = [
                'title' => (string)($rate['title'] ?? ''),
                'percent' => (float)($rate['percent'] ?? 0),
                'amount' => (float)($rate['tax_amount'] ?? 0),
            ];
        }

        return $result;
    }
}
