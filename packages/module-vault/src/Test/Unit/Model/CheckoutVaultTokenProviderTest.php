<?php
declare(strict_types=1);

namespace MageObsidian\Vault\Test\Unit\Model;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Model\Config as PaymentConfig;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentMethodListInterface;
use Magento\Vault\Model\CreditCardTokenFactory;
use Magento\Vault\Model\CustomerTokenManagement;
use Magento\Vault\Model\VaultPaymentInterface;
use MageObsidian\Vault\Model\CheckoutVaultTokenProvider;
use PHPUnit\Framework\TestCase;

/**
 * The checkout-side token provider. We assert it returns the customer's cards only
 * when an active vault method matches their provider, maps the provider to the
 * vault method code, and yields nothing for a guest or when no vault method is
 * active (the no-gateway case). Needs Magento Vault, so it skips when absent.
 */
class CheckoutVaultTokenProviderTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(PaymentMethodListInterface::class)) {
            $this->markTestSkipped('Magento Vault is not available in this runtime.');
        }
    }

    private function token(string $provider, string $details, string $hash): PaymentTokenInterface
    {
        $token = $this->createMock(PaymentTokenInterface::class);
        $token->method('getType')->willReturn(CreditCardTokenFactory::TOKEN_TYPE_CREDIT_CARD);
        $token->method('getPaymentMethodCode')->willReturn($provider);
        $token->method('getTokenDetails')->willReturn($details);
        $token->method('getPublicHash')->willReturn($hash);

        return $token;
    }

    private function vaultMethod(string $provider, string $code): VaultPaymentInterface
    {
        $method = $this->createMock(VaultPaymentInterface::class);
        $method->method('getProviderCode')->willReturn($provider);
        $method->method('getCode')->willReturn($code);

        return $method;
    }

    /**
     * @param PaymentTokenInterface[] $tokens
     * @param VaultPaymentInterface[] $activeMethods
     */
    private function provider(bool $loggedIn, array $tokens, array $activeMethods): CheckoutVaultTokenProvider
    {
        $session = $this->createMock(CustomerSession::class);
        $session->method('isLoggedIn')->willReturn($loggedIn);

        $management = $this->createMock(CustomerTokenManagement::class);
        $management->method('getCustomerSessionTokens')->willReturn($tokens);

        $list = $this->createMock(PaymentMethodListInterface::class);
        $list->method('getActiveList')->willReturn($activeMethods);

        $config = $this->createMock(PaymentConfig::class);
        $config->method('getCcTypes')->willReturn(['VI' => 'Visa']);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new CheckoutVaultTokenProvider($session, $management, $list, $config, new Json(), $storeManager);
    }

    public function testMapsTokenToTheActiveVaultMethodCode(): void
    {
        $tokens = $this->provider(
            true,
            [$this->token('braintree', '{"type":"VI","maskedCC":"1111","expirationDate":"12/2030"}', 'h1')],
            [$this->vaultMethod('braintree', 'braintree_cc_vault')]
        )->getTokens();

        $this->assertCount(1, $tokens);
        $this->assertSame('braintree_cc_vault', $tokens[0]['methodCode']);
        $this->assertSame('Visa', $tokens[0]['typeLabel']);
        $this->assertSame('1111', $tokens[0]['last4']);
        $this->assertSame('h1', $tokens[0]['publicHash']);
    }

    public function testGuestGetsNoTokens(): void
    {
        $this->assertSame([], $this->provider(false, [], [])->getTokens());
    }

    public function testNoActiveVaultMethodMeansNoTokens(): void
    {
        $tokens = $this->provider(
            true,
            [$this->token('braintree', '{"type":"VI"}', 'h1')],
            []
        )->getTokens();

        $this->assertSame([], $tokens);
    }

    public function testTokenFromAnInactiveProviderIsSkipped(): void
    {
        $tokens = $this->provider(
            true,
            [$this->token('authnetcim', '{"type":"VI"}', 'h1')],
            [$this->vaultMethod('braintree', 'braintree_cc_vault')]
        )->getTokens();

        $this->assertSame([], $tokens);
    }

    public function testItHandsTheCheckoutOneEntryPerVaultMethod(): void
    {
        $data = $this->provider(
            true,
            [$this->token('braintree', '{"type":"VI","maskedCC":"1111","expirationDate":"12/2030"}', 'h1')],
            [$this->vaultMethod('braintree', 'braintree_cc_vault')]
        )->getData();

        $this->assertSame(['braintree_cc_vault'], array_keys($data));
        $this->assertSame('h1', $data['braintree_cc_vault']['tokens'][0]['publicHash']);
    }

    public function testItHandsTheCheckoutNothingWithoutAStoredCard(): void
    {
        $this->assertSame([], $this->provider(false, [], [])->getData());
    }
}
