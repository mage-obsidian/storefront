<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Showcase project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Showcase\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use MageObsidian\Showcase\Model\Config;
use MageObsidian\Showcase\Model\FeaturePool;
use MageObsidian\Showcase\Model\Profile;

class Panel implements ArgumentInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly FeaturePool $pool,
        private readonly Profile $profile,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ModuleListInterface $moduleList
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->config->isEnabled() && $this->pool->available() !== [];
    }

    /**
     * Every feature with the value in force right now, whether it came from the
     * store view's own configuration or from what this visitor picked.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFeatures(): array
    {
        $selections = $this->profile->selections();
        $rows = [];

        foreach ($this->pool->available() as $feature) {
            // Reading it is what makes the plugin record the store's own value,
            // so the order of these two lines matters.
            $effective = (string)$this->scopeConfig->getValue($feature->path, ScopeInterface::SCOPE_STORE);
            $rows[] = [
                'key' => $feature->key,
                'label' => $feature->label,
                'description' => $feature->description,
                'type' => $feature->type->value,
                'choices' => $feature->choices(),
                'value' => $effective,
                'storeValue' => $this->profile->originalFor($feature->path) ?? $effective,
                'overridden' => isset($selections[$feature->key]),
                'module' => $this->moduleName($feature->module),
            ];
        }

        return $rows;
    }

    public function getParameter(): string
    {
        return Profile::REQUEST_PARAMETER;
    }

    public function getCookieName(): string
    {
        return Profile::COOKIE_NAME;
    }

    public function getSignature(): string
    {
        return $this->profile->signature();
    }

    public function hasOverrides(): bool
    {
        return $this->profile->selections() !== [];
    }

    /** Reads better on a demo than the raw module name. */
    private function moduleName(?string $module): string
    {
        if ($module === null || $this->moduleList->getOne($module) === null) {
            return '';
        }

        return str_contains($module, '_') ? explode('_', $module, 2)[1] : $module;
    }
}
