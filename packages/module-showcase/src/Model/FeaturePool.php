<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Showcase project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Showcase\Model;

use Magento\Framework\Module\ModuleListInterface;

/**
 * The registry of switchable features, and with it the allowlist: a config path
 * that is not declared here can never be reached by a visitor's cookie.
 *
 * Nothing but `ModuleListInterface` is injected on purpose. This pool is
 * resolved from inside a plugin on `ScopeConfigInterface`, so anything here that
 * read scope config would close a construction loop; the module list reads the
 * deployment config instead.
 */
class FeaturePool
{
    /** @var array<string, Feature>|null */
    private ?array $resolved = null;

    /**
     * @param array<string, array<string, mixed>> $features
     */
    public function __construct(
        private readonly ModuleListInterface $moduleList,
        private readonly array $features = []
    ) {
    }

    /**
     * Features whose owning module is present. A demo carries the switches for
     * modules it may not have installed yet, and those simply do not show up.
     *
     * @return array<string, Feature>
     */
    public function available(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $available = [];
        foreach ($this->features as $key => $definition) {
            $module = isset($definition['module']) ? (string)$definition['module'] : null;
            if (empty($definition['path']) || ($module !== null && $this->moduleList->getOne($module) === null)) {
                continue;
            }
            $available[$key] = new Feature(
                key: $key,
                path: (string)$definition['path'],
                label: (string)($definition['label'] ?? $key),
                type: FeatureType::from((string)($definition['type'] ?? FeatureType::Flag->value)),
                description: (string)($definition['description'] ?? ''),
                module: $module,
                options: array_map('strval', $definition['options'] ?? [])
            );
        }

        return $this->resolved = $available;
    }

    public function get(string $key): ?Feature
    {
        return $this->available()[$key] ?? null;
    }
}
