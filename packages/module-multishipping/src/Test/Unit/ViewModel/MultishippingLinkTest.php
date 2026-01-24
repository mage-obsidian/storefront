<?php
declare(strict_types=1);

namespace MageObsidian\Multishipping\Test\Unit\ViewModel;

use Magento\Framework\UrlInterface;
use Magento\Multishipping\Helper\Data as MultishippingHelper;
use MageObsidian\Multishipping\ViewModel\MultishippingLink;
use PHPUnit\Framework\TestCase;

/**
 * The cart entry-point VM. We assert the link self-gates on the native
 * availability helper and resolves the login-routed checkout URL. Needs Magento
 * Multishipping, so it skips when that module is absent.
 */
class MultishippingLinkTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(MultishippingHelper::class)) {
            $this->markTestSkipped('Magento Multishipping is not available in this runtime.');
        }
    }

    private function helper(bool $available): MultishippingHelper
    {
        $helper = $this->createMock(MultishippingHelper::class);
        $helper->method('isMultishippingCheckoutAvailable')->willReturn($available);

        return $helper;
    }

    private function url(): UrlInterface
    {
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn(string $route): string => 'https://shop.test/' . $route
        );

        return $url;
    }

    public function testIsAvailableMirrorsTheHelper(): void
    {
        $this->assertTrue((new MultishippingLink($this->helper(true), $this->url()))->isAvailable());
        $this->assertFalse((new MultishippingLink($this->helper(false), $this->url()))->isAvailable());
    }

    public function testCheckoutUrlRoutesThroughLogin(): void
    {
        $vm = new MultishippingLink($this->helper(true), $this->url());

        $this->assertSame('https://shop.test/multishipping/checkout/login', $vm->getCheckoutUrl());
    }
}
