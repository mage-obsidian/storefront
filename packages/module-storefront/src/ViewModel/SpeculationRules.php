<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use MageObsidian\Storefront\Model\Speculation\Eagerness;
use MageObsidian\Storefront\Model\Speculation\Mode;

/**
 * Builds the speculation-rules ruleset the browser uses to preload internal
 * links ahead of the click.
 *
 * `source: document` only ever matches `<a href>` navigations, which are GET.
 * Add-to-cart, wishlist and compare are forms POSTed through fetch in this
 * stack, so they are unreachable here — the exclusions below exist for the GET
 * links that really do mutate state (logout above all) and for destinations
 * where preloading buys nothing.
 */
class SpeculationRules implements ArgumentInterface
{
    public const string CONFIG_PATH = 'mage_obsidian/speculation/';
    public const string CONFIG_ENABLED = self::CONFIG_PATH . 'enabled';
    public const string CONFIG_MODE = self::CONFIG_PATH . 'mode';
    public const string CONFIG_EAGERNESS = self::CONFIG_PATH . 'eagerness';
    public const string CONFIG_EXCLUDE_PATHS = self::CONFIG_PATH . 'exclude_paths';
    public const string CONFIG_EXCLUDE_EXTENSIONS = self::CONFIG_PATH . 'exclude_extensions';
    public const string CONFIG_EXCLUDE_SELECTORS = self::CONFIG_PATH . 'exclude_selectors';

    private const string SOURCE_DOCUMENT = 'document';
    private const string MATCH_ALL = '/*';

    /**
     * Links the browser must never preload regardless of configuration: a
     * `nofollow` target is one the site already declined to vouch for, and the
     * `target` variants leave this document, so the speculation is wasted.
     */
    private const array UNSAFE_SELECTORS = [
        '[rel=nofollow]',
        '[target=_blank]',
        '[target=_parent]',
        '[target=_top]',
    ];

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::CONFIG_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return Mode
     */
    public function getMode(): Mode
    {
        return Mode::fromConfig($this->getConfigValue(self::CONFIG_MODE));
    }

    /**
     * @return Eagerness
     */
    public function getEagerness(): Eagerness
    {
        return Eagerness::fromConfig($this->getConfigValue(self::CONFIG_EAGERNESS));
    }

    /**
     * @return array
     */
    public function getRules(): array
    {
        return [
            $this->getMode()->value => [
                [
                    'source' => self::SOURCE_DOCUMENT,
                    'where' => $this->buildWhere(),
                    'eagerness' => $this->getEagerness()->value,
                ],
            ],
        ];
    }

    /**
     * @return string
     */
    public function getRulesJson(): string
    {
        return json_encode($this->getRules(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Path tokens the ruleset must refuse.
     *
     * @return string[]
     */
    public function getExcludedPaths(): array
    {
        // Each token has to stay within one path segment: a URLPattern group
        // cannot span a `/`, so "catalog/product_compare" would not compile.
        return array_values(array_filter(
            array_map(
                static fn(string $path): string => trim(trim($path), '/'),
                explode("\n", (string)$this->getConfigValue(self::CONFIG_EXCLUDE_PATHS))
            ),
            static fn(string $path): bool => $path !== '' && !str_contains($path, '/')
        ));
    }

    /**
     * File extensions the ruleset must refuse.
     *
     * @return string[]
     */
    public function getExcludedExtensions(): array
    {
        return array_values(array_filter(array_map(
            static fn(string $extension): string => ltrim(trim($extension), '.'),
            explode(',', (string)$this->getConfigValue(self::CONFIG_EXCLUDE_EXTENSIONS))
        )));
    }

    /**
     * Link selectors the ruleset must refuse.
     *
     * @return string[]
     */
    public function getExcludedSelectors(): array
    {
        return array_values(array_filter(array_map(
            'trim',
            explode("\n", (string)$this->getConfigValue(self::CONFIG_EXCLUDE_SELECTORS))
        )));
    }

    /**
     * @return array
     */
    private function buildWhere(): array
    {
        $conditions = [['href_matches' => self::MATCH_ALL]];

        if ($paths = $this->getExcludedPaths()) {
            $conditions[] = [
                'not' => ['href_matches' => '/*(' . implode('|', $paths) . ')/*'],
            ];
        }

        foreach ($this->getExcludedExtensions() as $extension) {
            $conditions[] = ['not' => ['href_matches' => '*.' . $extension]];
        }

        foreach ([...$this->getExcludedSelectors(), ...self::UNSAFE_SELECTORS] as $selector) {
            $conditions[] = ['not' => ['selector_matches' => $selector]];
        }

        return ['and' => $conditions];
    }

    /**
     * @param string $path
     * @return string|null
     */
    private function getConfigValue(string $path): ?string
    {
        $value = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);

        return is_scalar($value) ? (string)$value : null;
    }
}
