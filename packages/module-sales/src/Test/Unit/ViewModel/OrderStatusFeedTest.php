<?php
/**
 * This file is part of the MageObsidian - Sales project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Sales\Test\Unit\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Rss\UrlBuilderInterface;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Rss\Signature;
use MageObsidian\Sales\ViewModel\OrderStatusFeed;
use PHPUnit\Framework\TestCase;

class OrderStatusFeedTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Signature::class)) {
            $this->markTestSkipped('Magento Sales is not available in this runtime.');
        }
    }

    private function order(): Order
    {
        $order = $this->createStub(Order::class);
        $order->method('getId')->willReturn(7);
        $order->method('getIncrementId')->willReturn('000000007');
        $order->method('getCustomerId')->willReturn(3);

        return $order;
    }

    private function viewModel(bool $feedActive = true, ?Order $order = null): OrderStatusFeed
    {
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('isSetFlag')->willReturnCallback(
            static fn(string $path): bool => $feedActive && $path === 'rss/order/status'
        );

        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturnCallback(
            static fn(string $key): ?Order => $key === 'current_order' ? $order : null
        );

        $signature = $this->createStub(Signature::class);
        $signature->method('signData')->willReturnCallback(static fn(string $data): string => 'signed:' . $data);

        $urls = $this->createStub(UrlBuilderInterface::class);
        $urls->method('getUrl')->willReturnCallback(
            static fn(array $params): string => 'https://shop.test/rss/order/status?' . http_build_query($params['_query'])
                . '&type=' . $params['type']
        );

        return new OrderStatusFeed($config, $registry, $signature, $urls);
    }

    public function testTheLinkSubscribesToTheOrderThePageShows(): void
    {
        $link = $this->viewModel(order: $this->order())->link();

        $data = base64_encode((string)json_encode(['order_id' => 7, 'increment_id' => '000000007', 'customer_id' => 3]));
        $this->assertStringContainsString('type=order_status', $link);
        $this->assertStringContainsString('data=' . rawurlencode($data), $link);
        $this->assertStringContainsString('signature=' . rawurlencode('signed:' . $data), $link);
    }

    public function testWithTheFeedInactiveThereIsNoLink(): void
    {
        $this->assertSame('', $this->viewModel(feedActive: false, order: $this->order())->link());
    }

    public function testWithoutAnOrderOnThePageThereIsNoLink(): void
    {
        $this->assertSame('', $this->viewModel()->link());
    }
}
