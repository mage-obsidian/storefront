<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Model\Seo;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Storefront\Model\Config\SeoConfig;
use Throwable;

class WebManifestBuilder
{
    public const int SHORT_NAME_LENGTH = 12;

    private const array ICON_MIME_TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
    ];

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly StoreMediaUrl $mediaUrl,
        private readonly SeoConfig $config
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $name = $this->siteName();
        $startUrl = $this->startUrl();

        return array_filter(
            [
                'name' => $name,
                'short_name' => $this->shortName($name),
                'start_url' => $startUrl,
                'scope' => $startUrl,
                'display' => ManifestDisplay::fromConfig($this->config->getManifestDisplay())->value,
                'theme_color' => $this->config->getManifestThemeColor(),
                'background_color' => $this->config->getManifestBackgroundColor(),
                'icons' => $this->icons(),
            ],
            static fn(mixed $value): bool => $value !== '' && $value !== []
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function icons(): array
    {
        $icons = [];
        foreach ([$this->mediaUrl->getLogo(), $this->mediaUrl->getFavicon()] as $url) {
            if ($url === '' || isset($icons[$url])) {
                continue;
            }
            $icons[$url] = [
                'src' => $url,
                'sizes' => 'any',
                'type' => $this->mimeType($url),
            ];
        }

        return array_values(array_map(
            static fn(array $icon): array => array_filter($icon, static fn(string $v): bool => $v !== ''),
            $icons
        ));
    }

    private function mimeType(string $url): string
    {
        $extension = strtolower((string)pathinfo((string)parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return self::ICON_MIME_TYPES[$extension] ?? '';
    }

    private function shortName(string $name): string
    {
        if ($name === '' || mb_strlen($name) <= self::SHORT_NAME_LENGTH) {
            return $name;
        }

        return rtrim(mb_substr($name, 0, self::SHORT_NAME_LENGTH));
    }

    private function siteName(): string
    {
        try {
            return trim((string)$this->storeManager->getStore()->getFrontendName());
        } catch (Throwable) {
            return '';
        }
    }

    private function startUrl(): string
    {
        try {
            return (string)$this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_LINK);
        } catch (Throwable) {
            return '';
        }
    }
}
