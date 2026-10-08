<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\ViewModel;

use Magento\Checkout\Model\Session;
use Magento\Cookie\Helper\Cookie as CookieHelper;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\GoogleGtag\Model\Config\GtagConfig;
use Magento\Store\Model\StoreManagerInterface;

class GoogleAds implements ArgumentInterface
{
    public function __construct(
        private readonly GtagConfig $config,
        private readonly CookieHelper $cookies,
        private readonly StoreManagerInterface $stores,
        private readonly Session $session
    ) {
    }

    public function tag(): array
    {
        if (!$this->config->isGoogleAdwordsConfigurable()) {
            return [];
        }

        return [
            'isCookieRestrictionModeEnabled' => (bool)$this->cookies->isCookieRestrictionModeEnabled(),
            'currentWebsite' => (int)$this->stores->getWebsite()->getId(),
            'cookieName' => CookieHelper::IS_USER_ALLOWED_SAVE_COOKIE,
            'conversionId' => $this->config->getConversionId(),
            'src' => $this->config->getConversionGtagGlobalSiteTagSrc(),
        ];
    }

    public function conversion(): array
    {
        $tag = $this->tag();
        if ($tag === [] || !$this->config->isGoogleAdwordsActive()) {
            return $tag;
        }

        $order = $this->session->getLastRealOrder();
        if (!$order->getId()) {
            return $tag;
        }

        return $tag + [
            'conversion' => [
                'sendTo' => $this->config->getConversionId() . '/' . $this->config->getConversionLabel(),
                'value' => round((float)$order->getGrandTotal(), 2),
                'currency' => (string)$order->getOrderCurrencyCode(),
                'transactionId' => (string)$order->getIncrementId(),
            ],
        ];
    }
}
