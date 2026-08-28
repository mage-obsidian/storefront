<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Checkout\Model\Payment;

use Magento\Checkout\Model\CompositeConfigProvider;
use Magento\Checkout\Model\ConfigProviderInterface;
use Throwable;

class StoreScopedConfigProviders extends CompositeConfigProvider
{
    public function __construct(
        private readonly array $configProviders,
        private readonly array $excluded = []
    ) {
        parent::__construct($configProviders);
    }

    public function getConfig(): array
    {
        $config = [];

        foreach ($this->configProviders as $name => $provider) {
            if (!$provider instanceof ConfigProviderInterface || in_array((string)$name, $this->excluded, true)) {
                continue;
            }
            try {
                $config = array_merge_recursive($config, (array)$provider->getConfig());
            } catch (Throwable) {
                continue;
            }
        }

        return $config;
    }
}
