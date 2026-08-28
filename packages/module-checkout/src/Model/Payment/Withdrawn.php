<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Checkout\Model\Payment;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Api\PaymentMethodListInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class Withdrawn
{
    public const string ENABLED_PATH = 'checkout/mage_obsidian/withdraw_incompletable_methods';

    public const string RECORD = 'MageObsidian checkout withdrew a payment method';

    public function __construct(
        private readonly NeedsOwnUi $needsOwnUi,
        private readonly PaymentMethodListInterface $methodList,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger
    ) {
    }

    public function codes(array $renderedCodes): array
    {
        if (!$this->scopeConfig->isSetFlag(self::ENABLED_PATH, ScopeInterface::SCOPE_STORE)) {
            return [];
        }

        try {
            $withdrawn = [];
            foreach ($this->activeMethods() as $code => $title) {
                if (!$this->needsOwnUi->declares($code) || in_array($code, $renderedCodes, true)) {
                    continue;
                }
                $withdrawn[] = $code;
                $this->record($code, $title);
            }

            return $withdrawn;
        } catch (Throwable) {
            return [];
        }
    }

    private function activeMethods(): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $methods = [];

        foreach ($this->methodList->getActiveList($storeId) as $method) {
            $code = (string)$method->getCode();
            if ($code !== '') {
                $methods[$code] = (string)$method->getTitle();
            }
        }

        return $methods;
    }

    private function record(string $code, string $title): void
    {
        $this->logger->notice(sprintf(
            '%s: "%s" (%s) declares its own interface in the platform and no module supplies a renderer for it, '
            . 'so the checkout cannot take it to a placed order. Ship a renderer for it, exempt it as completable '
            . 'generically, or turn the method off.',
            self::RECORD,
            $title,
            $code
        ));
    }
}
