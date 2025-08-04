<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Model\Template\Extension;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Commerce formatting helpers for Twig. Registered on the engine's shared Twig
 * environment via the storefront's di.xml — the first consumer of the engine's
 * DI-driven extensibility (a module adding filters without touching the engine).
 *
 * PriceCurrency::format() emits markup (`<span class="price">…</span>`) when the
 * container is included, so both filters are flagged `is_safe => html`, like the
 * markup-emitting bridge helpers; the currency symbol is escaped inside Magento.
 */
class PriceExtension extends AbstractExtension
{
    /**
     * @param PriceCurrencyInterface $priceCurrency
     */
    public function __construct(
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getFilters(): array
    {
        $safeHtml = ['is_safe' => ['html']];

        return [
            new TwigFilter(
                'price',
                fn(mixed $amount, bool $includeContainer = true): string
                    => $this->priceCurrency->format($amount, $includeContainer),
                $safeHtml
            ),
            new TwigFilter(
                'currency',
                fn(mixed $amount, ?string $code = null, bool $includeContainer = true): string
                    => $this->priceCurrency->format(
                        $amount,
                        $includeContainer,
                        PriceCurrencyInterface::DEFAULT_PRECISION,
                        null,
                        $code
                    ),
                $safeHtml
            ),
        ];
    }
}
