<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\ViewModel\Head;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use MageObsidian\Storefront\Model\Config\SeoConfig;
use MageObsidian\Storefront\Model\Seo\CanonicalUrl;
use Throwable;

class SeoLinks implements ArgumentInterface
{
    public const string MANIFEST_ROUTE = 'mage_obsidian_storefront/manifest';

    public function __construct(
        private readonly CanonicalUrl $canonicalUrl,
        private readonly UrlInterface $url,
        private readonly SeoConfig $config
    ) {
    }

    public function getCanonicalUrl(): string
    {
        try {
            return $this->canonicalUrl->getOwn();
        } catch (Throwable) {
            return '';
        }
    }

    public function getManifestUrl(): string
    {
        if (!$this->config->isManifestEnabled()) {
            return '';
        }

        try {
            return (string)$this->url->getUrl(self::MANIFEST_ROUTE, ['_secure' => true]);
        } catch (Throwable) {
            return '';
        }
    }
}
