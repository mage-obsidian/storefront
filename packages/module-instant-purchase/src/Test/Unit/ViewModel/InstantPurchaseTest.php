<?php
declare(strict_types=1);

namespace MageObsidian\InstantPurchase\Test\Unit\ViewModel;

use Magento\Framework\UrlInterface;
use Magento\InstantPurchase\Model\Config;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\InstantPurchase\ViewModel\InstantPurchase;
use PHPUnit\Framework\TestCase;

/**
 * The PDP button VM. We assert it mirrors the native module flag, forwards the
 * button label, and resolves the secure place-order URL. Needs Magento
 * InstantPurchase, so it skips when that module is absent.
 */
class InstantPurchaseTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Config::class)) {
            $this->markTestSkipped('Magento InstantPurchase is not available in this runtime.');
        }
    }

    private function storeManager(): StoreManagerInterface
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $manager = $this->createMock(StoreManagerInterface::class);
        $manager->method('getStore')->willReturn($store);

        return $manager;
    }

    private function url(): UrlInterface
    {
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn(string $route, array $params = []): string =>
                'https://shop.test/' . $route . (($params['_secure'] ?? false) ? '?secure' : '')
        );

        return $url;
    }

    public function testIsEnabledMirrorsTheConfig(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('isModuleEnabled')->with(1)->willReturn(true);

        $this->assertTrue((new InstantPurchase($config, $this->storeManager(), $this->url()))->isEnabled());
    }

    public function testButtonTextIsForwarded(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getButtonText')->with(1)->willReturn('Instant Purchase');

        $this->assertSame(
            'Instant Purchase',
            (new InstantPurchase($config, $this->storeManager(), $this->url()))->getButtonText()
        );
    }

    public function testPurchaseUrlIsSecureRoute(): void
    {
        $vm = new InstantPurchase($this->createMock(Config::class), $this->storeManager(), $this->url());

        $this->assertSame('https://shop.test/instantpurchase/button/placeOrder?secure', $vm->getPurchaseUrl());
    }
}
