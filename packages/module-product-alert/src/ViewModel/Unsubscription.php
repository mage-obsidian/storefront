<?php
/**
 * This file is part of the MageObsidian - ProductAlert project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\ProductAlert\ViewModel;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\ProductAlert\Model\StockFactory;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

class Unsubscription implements ArgumentInterface
{
    public function __construct(
        private readonly StockFactory $stockFactory,
        private readonly Session $customerSession,
        private readonly StoreManagerInterface $storeManager,
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    public function hasSubscription(int $productId): bool
    {
        if ($productId <= 0) {
            return false;
        }
        try {
            $store = $this->storeManager->getStore();
            $subscription = $this->stockFactory->create()
                ->setCustomerId($this->customerSession->getCustomerId())
                ->setProductId($productId)
                ->setWebsiteId($store->getWebsiteId())
                ->setStoreId($store->getId())
                ->loadByParam();

            return (bool)$subscription->getId();
        } catch (Throwable) {
            return false;
        }
    }

    public function getProductName(int $productId): string
    {
        try {
            return (string)$this->productRepository->getById($productId)->getName();
        } catch (Throwable) {
            return '';
        }
    }
}
