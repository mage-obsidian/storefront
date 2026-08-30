<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Model\Seo;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

class StoreMediaUrl
{
    public const string LOGO_PATH = 'design/header/logo_src';
    public const string FAVICON_PATH = 'design/head/shortcut_icon';
    public const string LOGO_DIRECTORY = 'logo/';
    public const string FAVICON_DIRECTORY = 'favicon/';
    public const string SOCIAL_DIRECTORY = 'mage_obsidian/seo/';
    public const string PRODUCT_DIRECTORY = 'catalog/product/';

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getBase(): string
    {
        try {
            return (string)$this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        } catch (Throwable) {
            return '';
        }
    }

    public function forFile(string $directory, string $file): string
    {
        $file = ltrim(trim($file), '/');
        $base = $this->getBase();
        if ($file === '' || $base === '') {
            return '';
        }

        return $base . $directory . $file;
    }

    public function getLogo(): string
    {
        return $this->forFile(self::LOGO_DIRECTORY, $this->configValue(self::LOGO_PATH));
    }

    public function getFavicon(): string
    {
        return $this->forFile(self::FAVICON_DIRECTORY, $this->configValue(self::FAVICON_PATH));
    }

    public function getProductImage(string $file): string
    {
        $file = trim($file);
        if ($file === '' || $file === 'no_selection') {
            return '';
        }

        return $this->forFile(self::PRODUCT_DIRECTORY, ltrim($file, '/'));
    }

    private function configValue(string $path): string
    {
        return trim((string)$this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE));
    }
}
