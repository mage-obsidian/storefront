<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Vault project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Vault\ViewModel;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Api\PaymentMethodListInterface;
use MageObsidian\Checkout\ViewModel\PaymentRenderer;
use Throwable;

class VaultRenderer extends PaymentRenderer
{
    public function __construct(
        private readonly PaymentMethodListInterface $vaultMethodList,
        private readonly StoreManagerInterface $storeManager,
        string $component = ''
    ) {
        parent::__construct([], $component);
    }

    public function getMethodCodes(): array
    {
        try {
            $storeId = (int)$this->storeManager->getStore()->getId();
            $codes = array_values(array_filter(array_map(
                static fn ($method): string => (string)$method->getCode(),
                $this->vaultMethodList->getActiveList($storeId)
            )));
        } catch (Throwable) {
            $codes = [];
        }

        return $this->normaliseCodes($codes);
    }
}
