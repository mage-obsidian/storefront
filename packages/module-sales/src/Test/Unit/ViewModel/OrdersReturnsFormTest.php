<?php
declare(strict_types=1);

namespace MageObsidian\Sales\Test\Unit\ViewModel;

use Magento\Framework\UrlInterface;
use MageObsidian\Sales\ViewModel\OrdersReturnsForm;
use PHPUnit\Framework\TestCase;

/**
 * The guest lookup island's data source. It points the form at the guest view
 * controller and nothing else — the form key is the island's business, read from
 * the cookie, because this page is cacheable.
 */
class OrdersReturnsFormTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(UrlInterface::class)) {
            $this->markTestSkipped('Magento framework is not available in this runtime.');
        }
    }

    public function testActionUrlPointsAtTheGuestViewController(): void
    {
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->with('sales/guest/view')->willReturn('https://shop.test/sales/guest/view/');

        $viewModel = new OrdersReturnsForm($url);

        $this->assertSame('https://shop.test/sales/guest/view/', $viewModel->getActionUrl());
    }

    public function testDoesNotPrimeAFormKey(): void
    {
        $viewModel = new OrdersReturnsForm($this->createMock(UrlInterface::class));

        $this->assertFalse(method_exists($viewModel, 'getFormKey'));
    }
}
