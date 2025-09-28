<?php
/**
 * This file is part of the MageObsidian - Vault project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\Vault\ViewModel;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Payment\Model\Config as PaymentConfig;
use Magento\Vault\Model\CreditCardTokenFactory;
use Magento\Vault\Model\CustomerTokenManagement;

/**
 * Normalizes the customer's stored credit-card tokens for the account "Stored
 * Payment Methods" page. Card fields are read from each token's details JSON, so
 * the row renders without the provider-specific renderer the native template
 * relies on. Charging these tokens still requires a configured vault method.
 */
class StoredCards implements ArgumentInterface
{
    /**
     * @param CustomerTokenManagement $customerTokenManagement
     * @param PaymentConfig $paymentConfig
     * @param Json $serializer
     * @param UrlInterface $url
     */
    public function __construct(
        private readonly CustomerTokenManagement $customerTokenManagement,
        private readonly PaymentConfig $paymentConfig,
        private readonly Json $serializer,
        private readonly UrlInterface $url
    ) {
    }

    /**
     * The customer's stored credit cards, normalized for display.
     *
     * @return array<int, array{publicHash: string, last4: string, expiration: string, type: string, typeLabel: string}>
     */
    public function getCards(): array
    {
        $ccTypes = $this->paymentConfig->getCcTypes();
        $cards = [];
        foreach ($this->customerTokenManagement->getCustomerSessionTokens() as $token) {
            if ($token->getType() !== CreditCardTokenFactory::TOKEN_TYPE_CREDIT_CARD) {
                continue;
            }
            $details = $this->decodeDetails($token->getTokenDetails());
            $type = (string)($details['type'] ?? '');
            $cards[] = [
                'publicHash' => (string)$token->getPublicHash(),
                'last4' => (string)($details['maskedCC'] ?? ''),
                'expiration' => (string)($details['expirationDate'] ?? ''),
                'type' => $type,
                'typeLabel' => (string)($ccTypes[$type] ?? $type),
            ];
        }

        return $cards;
    }

    /**
     * Whether the customer has any stored credit card.
     *
     * @return bool
     */
    public function hasCards(): bool
    {
        return $this->getCards() !== [];
    }

    /**
     * URL of the vault delete-card controller (native, redirects back to the list).
     *
     * @return string
     */
    public function getDeleteUrl(): string
    {
        return $this->url->getUrl('vault/cards/deleteaction');
    }

    /**
     * Decode a token detail JSON payload, tolerating malformed input.
     *
     * @param string|null $json
     * @return array<string, mixed>
     */
    private function decodeDetails(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        try {
            $data = $this->serializer->unserialize($json);
        } catch (\InvalidArgumentException) {
            return [];
        }

        return is_array($data) ? $data : [];
    }
}
