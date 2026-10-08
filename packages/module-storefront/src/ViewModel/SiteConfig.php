<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\ViewModel;

use Magento\Framework\Session\Config\ConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

class SiteConfig implements ArgumentInterface
{
    private const int VERSION = 1;

    public function __construct(
        private readonly ConfigInterface $sessionConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $url
    ) {
    }

    public function toArray(): array
    {
        $store = $this->storeManager->getStore();

        return [
            'version' => self::VERSION,
            'cookie' => [
                'domain' => $this->domain(),
                'path' => (string)$this->sessionConfig->getCookiePath(),
                'lifetime' => (int)$this->sessionConfig->getCookieLifetime(),
                'secure' => (bool)$this->sessionConfig->getCookieSecure(),
                'sameSite' => strtolower((string)$this->sessionConfig->getCookieSameSite()),
            ],
            'websiteId' => (int)$this->storeManager->getWebsite()->getId(),
            'storeId' => (int)$store->getId(),
            'currency' => (string)$store->getCurrentCurrencyCode(),
            'noCookiesUrl' => $this->url->getUrl('cookie/index/noCookies'),
        ];
    }

    public function toJson(): string
    {
        return json_encode(
            $this->toArray(),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR
        );
    }

    private function domain(): string
    {
        $domain = (string)$this->sessionConfig->getCookieDomain();
        if ($domain === '' || $domain[0] === '.' || filter_var($domain, FILTER_VALIDATE_IP) !== false) {
            return $domain;
        }

        return '.' . $domain;
    }
}
