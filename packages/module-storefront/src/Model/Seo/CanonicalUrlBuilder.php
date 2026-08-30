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
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\UrlInterface;
use MageObsidian\Storefront\Model\Config\SeoConfig;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

class CanonicalUrlBuilder
{
    public const string PAGE_PARAM = 'p';
    public const string FIRST_PAGE = '1';

    private const array URL_SUFFIX_PATHS = [
        'catalog/seo/category_url_suffix',
        'catalog/seo/product_url_suffix',
    ];

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly HttpRequest $request,
        private readonly SeoConfig $config,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function build(): string
    {
        try {
            $baseUrl = (string)$this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_LINK);
        } catch (Throwable) {
            return '';
        }

        if ($baseUrl === '') {
            return '';
        }

        $requestUri = (string)$this->request->getRequestUri();
        $path = $this->relativePath($baseUrl, $requestUri);
        $query = $this->keptQuery($requestUri);

        return rtrim($baseUrl, '/') . $path . ($query === '' ? '' : '?' . $query);
    }

    private function relativePath(string $baseUrl, string $requestUri): string
    {
        $path = (string)parse_url($requestUri, PHP_URL_PATH);
        if ($path === '') {
            $path = '/';
        }

        $basePath = rtrim((string)parse_url($baseUrl, PHP_URL_PATH), '/');
        if ($basePath !== '') {
            if ($path === $basePath) {
                return '/';
            }
            if (str_starts_with($path, $basePath . '/')) {
                $path = substr($path, strlen($basePath));
            }
        }

        if ($this->storeUrlsEndInASlash()) {
            return $path;
        }

        $trimmed = rtrim($path, '/');

        return $trimmed === '' ? '/' : $trimmed;
    }

    private function keptQuery(string $requestUri): string
    {
        $allowed = $this->config->getCanonicalQueryParams();
        if ($allowed === []) {
            return '';
        }

        parse_str((string)parse_url($requestUri, PHP_URL_QUERY), $params);
        $kept = array_filter(
            array_intersect_key($params, array_flip($allowed)),
            static fn(mixed $value, string $name): bool => is_scalar($value)
                && trim((string)$value) !== ''
                && !($name === self::PAGE_PARAM && trim((string)$value) === self::FIRST_PAGE),
            ARRAY_FILTER_USE_BOTH
        );
        if ($kept === []) {
            return '';
        }

        ksort($kept);

        return http_build_query($kept);
    }

    private function storeUrlsEndInASlash(): bool
    {
        foreach (self::URL_SUFFIX_PATHS as $path) {
            $suffix = trim((string)$this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE));
            if ($suffix !== '' && str_ends_with($suffix, '/')) {
                return true;
            }
        }

        return false;
    }
}
