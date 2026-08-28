<?php
/**
 * This file is part of the MageObsidian - Vault project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\Vault\Model;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Model\Config as PaymentConfig;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Api\PaymentMethodListInterface;
use Magento\Vault\Model\CreditCardTokenFactory;
use Magento\Vault\Model\CustomerTokenManagement;
use MageObsidian\Checkout\Api\MethodDataProviderInterface;
use Throwable;

/**
 * Feeds the checkout island the logged-in customer's saved cards. Each token is
 * matched to an ACTIVE vault payment method (by provider code) so the island can
 * place the order against the right method with the token's public hash. With no
 * configured tokenizing gateway there are no active vault methods, so the list is
 * empty and the checkout is unchanged.
 */
class CheckoutVaultTokenProvider implements MethodDataProviderInterface
{
    /**
     * @param CustomerSession $customerSession
     * @param CustomerTokenManagement $customerTokenManagement
     * @param PaymentMethodListInterface $vaultMethodList
     * @param PaymentConfig $paymentConfig
     * @param Json $serializer
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly CustomerTokenManagement $customerTokenManagement,
        private readonly PaymentMethodListInterface $vaultMethodList,
        private readonly PaymentConfig $paymentConfig,
        private readonly Json $serializer,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getData(): array
    {
        $data = [];

        foreach ($this->getTokens() as $token) {
            $code = (string)$token['methodCode'];
            $data[$code]['tokens'][] = $token;
        }

        return $data;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTokens(): array
    {
        if (!$this->customerSession->isLoggedIn()) {
            return [];
        }

        try {
            $methodByProvider = $this->activeVaultMethods();
            if ($methodByProvider === []) {
                return [];
            }

            $ccTypes = $this->paymentConfig->getCcTypes();
            $tokens = [];
            foreach ($this->customerTokenManagement->getCustomerSessionTokens() as $token) {
                if ($token->getType() !== CreditCardTokenFactory::TOKEN_TYPE_CREDIT_CARD) {
                    continue;
                }
                $provider = (string)$token->getPaymentMethodCode();
                if (!isset($methodByProvider[$provider])) {
                    continue;
                }
                $details = $this->decodeDetails($token->getTokenDetails());
                $type = (string)($details['type'] ?? '');
                $tokens[] = [
                    'publicHash' => (string)$token->getPublicHash(),
                    'methodCode' => $methodByProvider[$provider],
                    'last4' => (string)($details['maskedCC'] ?? ''),
                    'type' => $type,
                    'typeLabel' => (string)($ccTypes[$type] ?? $type),
                    'expiration' => (string)($details['expirationDate'] ?? ''),
                ];
            }

            return $tokens;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Active vault methods for the current store, keyed by provider code.
     *
     * @return array<string, string>
     */
    private function activeVaultMethods(): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $map = [];
        foreach ($this->vaultMethodList->getActiveList($storeId) as $method) {
            $map[(string)$method->getProviderCode()] = (string)$method->getCode();
        }

        return $map;
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
