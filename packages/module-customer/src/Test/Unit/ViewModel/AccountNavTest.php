<?php
declare(strict_types=1);

namespace MageObsidian\Customer\Test\Unit\ViewModel;

use Magento\Framework\App\Request\Http;
use Magento\Framework\UrlInterface;
use MageObsidian\Customer\ViewModel\AccountNav;
use PHPUnit\Framework\TestCase;

/**
 * The account sidebar links come from di.xml; we assert injected links are sorted,
 * carry their label/url, and that the one matching the current full action name is
 * flagged active.
 */
class AccountNavTest extends TestCase
{
    private const LINKS = [
        'dashboard' => ['route' => 'customer/account', 'match' => 'customer_account_index', 'label' => 'Dashboard', 'sortOrder' => 10],
        'orders' => ['route' => 'sales/order/history', 'match' => 'sales_order', 'label' => 'My Orders', 'sortOrder' => 20],
        'edit' => ['route' => 'customer/account/edit', 'match' => 'customer_account_edit', 'label' => 'Account Information', 'sortOrder' => 40],
    ];

    protected function setUp(): void
    {
        if (!class_exists(Http::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    private function buildViewModel(string $fullActionName, ?array $links = null): AccountNav
    {
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(static fn (string $route): string => '/' . $route);

        $request = $this->createMock(Http::class);
        $request->method('getFullActionName')->willReturn($fullActionName);

        return new AccountNav($url, $request, $links ?? self::LINKS);
    }

    public function testReturnsInjectedLinksWithUrlAndLabel(): void
    {
        $links = $this->buildViewModel('customer_account_index')->getLinks();
        $ids = array_column($links, 'id');

        $this->assertSame(['dashboard', 'orders', 'edit'], $ids);
        $this->assertSame('/customer/account', $links[0]['url']);
        $this->assertSame('Dashboard', $links[0]['label']);
    }

    public function testSortsLinksBySortOrder(): void
    {
        $links = $this->buildViewModel('cms_index_index', [
            'edit' => ['route' => 'customer/account/edit', 'match' => 'customer_account_edit', 'label' => 'Edit', 'sortOrder' => 40],
            'dashboard' => ['route' => 'customer/account', 'match' => 'customer_account_index', 'label' => 'Dashboard', 'sortOrder' => 10],
        ])->getLinks();

        $this->assertSame(['dashboard', 'edit'], array_column($links, 'id'));
    }

    public function testSkipsLinksWithoutARoute(): void
    {
        $links = $this->buildViewModel('cms_index_index', [
            'broken' => ['match' => 'x', 'label' => 'Broken'],
            'dashboard' => ['route' => 'customer/account', 'match' => 'customer_account_index', 'label' => 'Dashboard'],
        ])->getLinks();

        $this->assertSame(['dashboard'], array_column($links, 'id'));
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
