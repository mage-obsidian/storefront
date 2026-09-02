<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Model\PageBuilder;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class MapProvider
{
    public const string CONFIG_PATH = 'cms/pagebuilder/google_maps_api_key';

    private const string MARKER = 'data-content-type="map"';

    private const string PATTERN = '#<div\b[^>]*\bdata-content-type="map"[^>]*>#i';

    private const string ATTRIBUTE = 'data-map-api-key';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function inject(string $html): string
    {
        if (!str_contains($html, self::MARKER)) {
            return $html;
        }

        $key = trim((string)$this->scopeConfig->getValue(self::CONFIG_PATH, ScopeInterface::SCOPE_STORE));
        if ($key === '') {
            return $html;
        }

        $attribute = sprintf(
            ' %s="%s"',
            self::ATTRIBUTE,
            htmlspecialchars($key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );

        return (string)preg_replace_callback(
            self::PATTERN,
            static fn (array $match): string => stripos($match[0], self::ATTRIBUTE . '=') !== false
                ? $match[0]
                : substr($match[0], 0, -1) . $attribute . '>',
            $html
        );
    }
}
