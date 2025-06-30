<?php
declare(strict_types=1);

namespace MageObsidian\Customer\Test\Unit\ViewModel;

use Magento\Framework\UrlInterface;
use MageObsidian\Customer\ViewModel\AccountState;
use PHPUnit\Framework\TestCase;

/**
 * The header account island's URL source. Each getter maps to a native customer
 * route; we assert the route→URL wiring against a stubbed UrlInterface. Needs the
 * framework interface, so it runs in a Magento root.
 */
class AccountStateTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(UrlInterface::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    private function buildViewModel(): AccountState
    {
        $url = $this->createMock(UrlInterface::class);
        // Echo the route back so each getter's mapping is observable.
        $url->method('getUrl')->willReturnCallback(static fn (string $route): string => '/' . $route);

        return new AccountState($url);
    }

    public function testResolvesNativeAccountRoutes(): void
    {
        $vm = $this->buildViewModel();

        $this->assertSame('/customer/account', $vm->getAccountUrl());
        $this->assertSame('/customer/account/login', $vm->getLoginUrl());
        $this->assertSame('/customer/account/create', $vm->getRegisterUrl());
        $this->assertSame('/sales/order/history', $vm->getOrdersUrl());
        $this->assertSame('/customer/address', $vm->getAddressesUrl());
        $this->assertSame('/customer/account/logout', $vm->getLogoutUrl());
    }
}
