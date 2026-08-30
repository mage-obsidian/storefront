<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class SeoConfig
{
    public const string SEO_PATH = 'mage_obsidian/seo/';
    public const string CANONICAL_ENABLED = self::SEO_PATH . 'canonical_enabled';
    public const string CANONICAL_QUERY_PARAMS = self::SEO_PATH . 'canonical_query_params';
    public const string SOCIAL_META_ENABLED = self::SEO_PATH . 'social_meta_enabled';
    public const string SOCIAL_IMAGE = self::SEO_PATH . 'social_image';
    public const string TWITTER_SITE = self::SEO_PATH . 'twitter_site';
    public const string ROBOTS_DIRECTIVES = self::SEO_PATH . 'robots_directives';
    public const string META_DESCRIPTION_FALLBACK = self::SEO_PATH . 'meta_description_fallback';
    public const string MANIFEST_ENABLED = self::SEO_PATH . 'manifest_enabled';
    public const string MANIFEST_DISPLAY = self::SEO_PATH . 'manifest_display';
    public const string MANIFEST_THEME_COLOR = self::SEO_PATH . 'manifest_theme_color';
    public const string MANIFEST_BACKGROUND_COLOR = self::SEO_PATH . 'manifest_background_color';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isCanonicalEnabled(): bool
    {
        return $this->flag(self::CANONICAL_ENABLED);
    }

    /**
     * @return array<int, string>
     */
    public function getCanonicalQueryParams(): array
    {
        return $this->tokens($this->value(self::CANONICAL_QUERY_PARAMS));
    }

    public function isSocialMetaEnabled(): bool
    {
        return $this->flag(self::SOCIAL_META_ENABLED);
    }

    public function getSocialImage(): string
    {
        return $this->value(self::SOCIAL_IMAGE);
    }

    public function getTwitterSite(): string
    {
        return $this->value(self::TWITTER_SITE);
    }

    /**
     * @return array<int, string>
     */
    public function getRobotsDirectives(): array
    {
        return $this->tokens($this->value(self::ROBOTS_DIRECTIVES));
    }

    public function isMetaDescriptionFallbackEnabled(): bool
    {
        return $this->flag(self::META_DESCRIPTION_FALLBACK);
    }

    public function isManifestEnabled(): bool
    {
        return $this->flag(self::MANIFEST_ENABLED);
    }

    public function getManifestDisplay(): string
    {
        return $this->value(self::MANIFEST_DISPLAY);
    }

    public function getManifestThemeColor(): string
    {
        return $this->value(self::MANIFEST_THEME_COLOR);
    }

    public function getManifestBackgroundColor(): string
    {
        return $this->value(self::MANIFEST_BACKGROUND_COLOR);
    }

    private function flag(string $path): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE);
    }

    private function value(string $path): string
    {
        return trim((string)$this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE));
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $raw): array
    {
        $tokens = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_map('trim', $tokens)));
    }
}
