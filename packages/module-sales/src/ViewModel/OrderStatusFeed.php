<?php
/**
 * This file is part of the MageObsidian - Sales project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Sales\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Rss\UrlBuilderInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Model\Rss\Signature;
use Magento\Store\Model\ScopeInterface;

class OrderStatusFeed implements ArgumentInterface
{
    private const string FEED_FLAG = 'rss/order/status';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Registry $registry,
        private readonly Signature $signature,
        private readonly UrlBuilderInterface $rssUrlBuilder
    ) {
    }

    public function link(): string
    {
        $order = $this->registry->registry('current_order');
        if (!$this->scopeConfig->isSetFlag(self::FEED_FLAG, ScopeInterface::SCOPE_STORE) || !$order?->getId()) {
            return '';
        }

        $data = base64_encode((string)json_encode([
            'order_id' => $order->getId(),
            'increment_id' => $order->getIncrementId(),
            'customer_id' => $order->getCustomerId(),
        ]));

        return $this->rssUrlBuilder->getUrl([
            'type' => 'order_status',
            '_secure' => true,
            '_query' => ['data' => $data, 'signature' => $this->signature->signData($data)],
        ]);
    }
}
