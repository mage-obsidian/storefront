<?php
declare(strict_types=1);

namespace MageObsidian\Sales\Test\Unit\ViewModel;

use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use MageObsidian\Sales\ViewModel\OrdersReturnsForm;
use PHPUnit\Framework\TestCase;

/**
 * The guest lookup island's data source. We assert it points the form at the
 * guest view controller and surfaces the server-primed form key the island POSTs.
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

        $viewModel = new OrdersReturnsForm($url, $this->createMock(FormKey::class));

        $this->assertSame('https://shop.test/sales/guest/view/', $viewModel->getActionUrl());
    }

    public function testFormKeyComesFromTheFrameworkFormKey(): void
    {
        $formKey = $this->createMock(FormKey::class);
        $formKey->method('getFormKey')->willReturn('abc123');

        $viewModel = new OrdersReturnsForm($this->createMock(UrlInterface::class), $formKey);

        $this->assertSame('abc123', $viewModel->getFormKey());
    }
}
