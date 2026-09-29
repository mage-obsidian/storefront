<?php
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Checkout\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use MageObsidian\Checkout\ViewModel\CheckoutConfig;

/**
 * The checkout island's private seed, served as customer-data.
 *
 * A cacheable page may only carry the store-scoped half, so the quote, the auth
 * mode and the vault tokens travel here. Riding customer-data rather than a
 * bespoke endpoint inherits the localStorage mirror and the version cookie that
 * already invalidates on every cart mutation.
 */
class CheckoutSection implements SectionSourceInterface
{
    /**
     * @param CheckoutConfig $checkoutConfig
     */
    public function __construct(
        private readonly CheckoutConfig $checkoutConfig
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getSectionData(): array
    {
        return $this->checkoutConfig->getPrivateData();
    }
}
