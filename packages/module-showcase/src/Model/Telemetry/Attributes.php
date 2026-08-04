<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Showcase project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Showcase\Model\Telemetry;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Showcase\Model\Config;
use MageObsidian\Showcase\Model\FeaturePool;
use MageObsidian\Showcase\Model\FeatureType;
use MageObsidian\Showcase\Model\Profile;

/**
 * The dimensions a monitoring agent needs to tell one request's feature set from
 * another's, so a demo can be measured instead of described.
 *
 * Reading each path through scope config is what makes these the values actually
 * in force: the reads go through `ApplyShowcaseOverrides`, so the store view's
 * own setting and the visitor's override are already composed by the time they
 * get here.
 */
class Attributes
{
    public const string PREFIX = 'obsidian.';

    public const string FRAGMENT_PARAMETER = 'obsidian_fragment';

    /** What a visitor who changed nothing reports as, so the value is faceted like any other. */
    public const string NO_OVERRIDES = 'store';

    public function __construct(
        private readonly Config $config,
        private readonly FeaturePool $pool,
        private readonly Profile $profile,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function collect(): array
    {
        if (!$this->config->isEnabled()) {
            return [];
        }

        $collected = [
            self::PREFIX . 'profile' => $this->profile->signature() ?: self::NO_OVERRIDES,
            self::PREFIX . 'store' => $this->storeManager->getStore()->getCode(),
            self::PREFIX . 'fragment' => $this->fragment(),
        ];
        foreach ($this->pool->available() as $feature) {
            $collected[self::PREFIX . $feature->key] = (string)$this->scopeConfig->getValue(
                $feature->path,
                ScopeInterface::SCOPE_STORE
            );
        }

        return $collected;
    }

    /**
     * `$_GET` rather than the request object: the module that owns this flag
     * takes it back out of the request before anything renders, so by the time a
     * request is over the only place it survives is the superglobal.
     */
    private function fragment(): string
    {
        return ($_GET[self::FRAGMENT_PARAMETER] ?? null) === FeatureType::FLAG_ON
            ? FeatureType::FLAG_ON
            : FeatureType::FLAG_OFF;
    }
}
