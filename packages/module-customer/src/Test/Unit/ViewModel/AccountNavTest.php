<?php
declare(strict_types=1);

namespace MageObsidian\Customer\Test\Unit\ViewModel;

use Magento\Framework\App\Request\Http;
use Magento\Framework\UrlInterface;
use MageObsidian\Customer\ViewModel\AccountNav;
use PHPUnit\Framework\TestCase;

/**
 * The account sidebar links. We assert the canonical set and that the link
 * matching the current full action name is flagged active.
 */
class AccountNavTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Http::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    private function buildViewModel(string $fullActionName): AccountNav
    {
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(static fn (string $route): string => '/' . $route);

        $request = $this->createMock(Http::class);
        $request->method('getFullActionName')->willReturn($fullActionName);

        return new AccountNav($url, $request);
    }

    public function testReturnsCanonicalLinkSet(): void
    {
        $links = $this->buildViewModel('customer_account_index')->getLinks();
        $ids = array_column($links, 'id');

        $this->assertSame(['dashboard', 'orders', 'address', 'edit', 'newsletter'], $ids);
        $this->assertSame('/customer/account', $links[0]['url']);
    }

    public function testFlagsTheActiveLinkByFullActionName(): void
    {
        $links = $this->buildViewModel('customer_account_edit')->getLinks();
        $active = array_values(array_filter($links, static fn (array $l): bool => $l['active']));

        $this->assertCount(1, $active);
        $this->assertSame('edit', $active[0]['id']);
    }

    public function testOrdersMatchByPrefix(): void
    {
        $links = $this->buildViewModel('sales_order_view')->getLinks();
        $active = array_values(array_filter($links, static fn (array $l): bool => $l['active']));

        $this->assertSame('orders', $active[0]['id']);
    }

    public function testNoActiveLinkOnUnrelatedPage(): void
    {
        $links = $this->buildViewModel('cms_index_index')->getLinks();
        $this->assertSame([], array_filter($links, static fn (array $l): bool => $l['active']));
    }
}
