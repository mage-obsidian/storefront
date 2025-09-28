<?php
declare(strict_types=1);

namespace MageObsidian\Vault\Test\Unit\ViewModel;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Payment\Model\Config as PaymentConfig;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\CreditCardTokenFactory;
use Magento\Vault\Model\CustomerTokenManagement;
use MageObsidian\Vault\ViewModel\StoredCards;
use PHPUnit\Framework\TestCase;

/**
 * The account card-list VM. We assert it keeps only credit-card tokens, reads the
 * card fields from the token detail JSON, maps the CC type to its label, and
 * tolerates malformed detail payloads. Needs Magento Vault, so it skips when that
 * module is absent.
 */
class StoredCardsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(CustomerTokenManagement::class)) {
            $this->markTestSkipped('Magento Vault is not available in this runtime.');
        }
    }

    private function token(string $type, ?string $details, string $hash): PaymentTokenInterface
    {
        $token = $this->createMock(PaymentTokenInterface::class);
        $token->method('getType')->willReturn($type);
        $token->method('getTokenDetails')->willReturn($details);
        $token->method('getPublicHash')->willReturn($hash);

        return $token;
    }

    /**
     * @param PaymentTokenInterface[] $tokens
     */
    private function vm(array $tokens): StoredCards
    {
        $management = $this->createMock(CustomerTokenManagement::class);
        $management->method('getCustomerSessionTokens')->willReturn($tokens);

        $config = $this->createMock(PaymentConfig::class);
        $config->method('getCcTypes')->willReturn(['VI' => 'Visa', 'MC' => 'MasterCard']);

        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn(string $route): string => 'https://shop.test/' . $route
        );

        return new StoredCards($management, $config, new Json(), $url);
    }

    public function testKeepsOnlyCreditCardTokensAndNormalizesDetails(): void
    {
        $vm = $this->vm([
            $this->token(
                CreditCardTokenFactory::TOKEN_TYPE_CREDIT_CARD,
                '{"type":"VI","maskedCC":"1111","expirationDate":"12/2030"}',
                'hash-visa'
            ),
            $this->token('account', '{"payerEmail":"a@b.test"}', 'hash-account'),
        ]);

        $cards = $vm->getCards();

        $this->assertCount(1, $cards);
        $this->assertSame([
            'publicHash' => 'hash-visa',
            'last4' => '1111',
            'expiration' => '12/2030',
            'type' => 'VI',
            'typeLabel' => 'Visa',
        ], $cards[0]);
        $this->assertTrue($vm->hasCards());
    }

    public function testUnknownTypeFallsBackToItsCode(): void
    {
        $vm = $this->vm([
            $this->token(
                CreditCardTokenFactory::TOKEN_TYPE_CREDIT_CARD,
                '{"type":"ZZ","maskedCC":"4444","expirationDate":"01/2031"}',
                'hash-zz'
            ),
        ]);

        $this->assertSame('ZZ', $vm->getCards()[0]['typeLabel']);
    }

    public function testMalformedDetailsYieldEmptyFields(): void
    {
        $vm = $this->vm([
            $this->token(CreditCardTokenFactory::TOKEN_TYPE_CREDIT_CARD, 'not-json', 'hash-broken'),
        ]);

        $card = $vm->getCards()[0];
        $this->assertSame('', $card['last4']);
        $this->assertSame('', $card['expiration']);
        $this->assertSame('hash-broken', $card['publicHash']);
    }

    public function testNoTokensMeansNoCards(): void
    {
        $vm = $this->vm([]);

        $this->assertSame([], $vm->getCards());
        $this->assertFalse($vm->hasCards());
    }

    public function testDeleteUrlRoutesToVaultDeleteAction(): void
    {
        $this->assertSame(
            'https://shop.test/vault/cards/deleteaction',
            $this->vm([])->getDeleteUrl()
        );
    }
}
