<?php
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\Model\Payment;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Api\PaymentMethodListInterface;
use Magento\Payment\Api\Data\PaymentMethodInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Checkout\Model\Payment\MethodConfig;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MethodConfigTest extends TestCase
{
    private function methodConfig(array $payment, array $activeCodes, array $keyAliases = []): MethodConfig
    {
        $providers = $this->createStub(ConfigProviderInterface::class);
        $providers->method('getConfig')->willReturn(['payment' => $payment]);

        return new MethodConfig(
            $providers,
            $this->methodList($activeCodes),
            $this->storeManager(),
            $keyAliases
        );
    }

    private function methodList(array $codes): PaymentMethodListInterface
    {
        $methods = array_map(
            function (string $code): PaymentMethodInterface {
                $method = $this->createStub(PaymentMethodInterface::class);
                $method->method('getCode')->willReturn($code);

                return $method;
            },
            $codes
        );

        $list = $this->createStub(PaymentMethodListInterface::class);
        $list->method('getActiveList')->willReturn($methods);

        return $list;
    }

    private function storeManager(): StoreManagerInterface
    {
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $manager = $this->createStub(StoreManagerInterface::class);
        $manager->method('getStore')->willReturn($store);

        return $manager;
    }

    public function testAnInactiveMethodIsNotPublished(): void
    {
        $config = $this->methodConfig(
            ['checkmo' => ['title' => 'Check'], 'banktransfer' => ['title' => 'Wire']],
            ['checkmo']
        );

        $this->assertSame(['checkmo' => ['title' => 'Check']], $config->getConfig());
    }

    public function testACamelCaseKeyReachesItsMethodCode(): void
    {
        $config = $this->methodConfig(
            ['paypalExpress' => ['clientId' => 'abc']],
            ['paypal_express']
        );

        $this->assertSame(['paypalExpress' => ['clientId' => 'abc']], $config->getConfig());
    }

    public function testADeclaredAliasReachesItsMethodCode(): void
    {
        $config = $this->methodConfig(
            ['paypalBillingAgreement' => ['x' => 1]],
            ['paypal_billing_agreement'],
            ['paypalBillingAgreement' => 'paypal_billing_agreement']
        );

        $this->assertSame(['paypalBillingAgreement' => ['x' => 1]], $config->getConfig());
    }

    public function testAKeyThatNamesNoActiveMethodIsDropped(): void
    {
        $config = $this->methodConfig(
            ['paypalIframe' => ['x' => 1], '' => ['y' => 2], 'checkmo' => ['title' => 'Check']],
            ['checkmo']
        );

        $this->assertSame(['checkmo' => ['title' => 'Check']], $config->getConfig());
    }

    public function testNothingIsPublishedWithoutAnActiveMethod(): void
    {
        $config = $this->methodConfig(['checkmo' => ['title' => 'Check']], []);

        $this->assertSame([], $config->getConfig());
    }

    public function testAFailingProviderLeavesTheCheckoutWithoutMethodConfig(): void
    {
        $providers = $this->createStub(ConfigProviderInterface::class);
        $providers->method('getConfig')->willThrowException(new RuntimeException('boom'));

        $config = new MethodConfig($providers, $this->methodList(['checkmo']), $this->storeManager());

        $this->assertSame([], $config->getConfig());
    }

    public function testTheCardFormIsAbsentWithoutAnActiveCardMethod(): void
    {
        $config = $this->methodConfig(
            [
                'checkmo' => ['title' => 'Check'],
                'ccform' => [
                    'availableTypes' => ['authorizenet' => ['VI', 'MC']],
                    'months' => ['authorizenet' => ['1' => '01']],
                    'icons' => ['VI' => ['url' => 'visa.png']],
                ],
            ],
            ['checkmo']
        );

        $this->assertArrayNotHasKey('ccform', $config->getConfig());
    }

    public function testTheCardFormIsNarrowedToTheActiveCardMethod(): void
    {
        $config = $this->methodConfig(
            [
                'ccform' => [
                    'availableTypes' => ['authorizenet' => ['VI'], 'braintree' => ['MC']],
                    'hasVerification' => ['authorizenet' => true, 'braintree' => false],
                    'icons' => ['VI' => ['url' => 'visa.png']],
                ],
            ],
            ['authorizenet']
        );

        $this->assertSame(
            [
                'ccform' => [
                    'availableTypes' => ['authorizenet' => ['VI']],
                    'hasVerification' => ['authorizenet' => true],
                    'icons' => ['VI' => ['url' => 'visa.png']],
                ],
            ],
            $config->getConfig()
        );
    }
}
