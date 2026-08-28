<?php
declare(strict_types=1);

namespace MageObsidian\Checkout\Test\Unit\Model\Payment;

use Magento\Checkout\Model\ConfigProviderInterface;
use MageObsidian\Checkout\Model\Payment\StoreScopedConfigProviders;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class StoreScopedConfigProvidersTest extends TestCase
{
    private function provider(array $config): ConfigProviderInterface
    {
        $provider = $this->createStub(ConfigProviderInterface::class);
        $provider->method('getConfig')->willReturn($config);

        return $provider;
    }

    public function testAnExcludedProviderIsNeverAsked(): void
    {
        $quoteDependent = $this->createMock(ConfigProviderInterface::class);
        $quoteDependent->expects($this->never())->method('getConfig');

        $providers = new StoreScopedConfigProviders(
            [
                'checkout_default_config_provider' => $quoteDependent,
                'offline_payment_checkmo_config_provider' => $this->provider(['payment' => ['checkmo' => ['x' => 1]]]),
            ],
            ['checkout_default_config_provider']
        );

        $this->assertSame(['payment' => ['checkmo' => ['x' => 1]]], $providers->getConfig());
    }

    public function testTheRestSurviveAProviderThatThrows(): void
    {
        $broken = $this->createStub(ConfigProviderInterface::class);
        $broken->method('getConfig')->willThrowException(new RuntimeException('gateway is down'));

        $providers = new StoreScopedConfigProviders([
            'broken' => $broken,
            'offline_payment_checkmo_config_provider' => $this->provider(['payment' => ['checkmo' => ['x' => 1]]]),
        ]);

        $this->assertSame(['payment' => ['checkmo' => ['x' => 1]]], $providers->getConfig());
    }

    public function testEveryProviderIsMergedWhenNoneIsExcluded(): void
    {
        $providers = new StoreScopedConfigProviders([
            'a' => $this->provider(['payment' => ['checkmo' => ['x' => 1]]]),
            'b' => $this->provider(['payment' => ['free' => ['y' => 2]]]),
        ]);

        $this->assertSame(
            ['payment' => ['checkmo' => ['x' => 1], 'free' => ['y' => 2]]],
            $providers->getConfig()
        );
    }
}
