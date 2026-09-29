<?php
/**
 * This file is part of the MageObsidian - Showcase project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Showcase\Model;

use Magento\Framework\App\DeploymentConfig;

/**
 * The switch lives in `app/etc/env.php`, not in the admin, and that is the whole
 * point: this module rewrites configuration from a cookie, so the decision to
 * allow that has to sit somewhere a store operator cannot reach by accident and
 * the module itself can never rewrite. Reading the deployment config also keeps
 * it out of scope config, which this module plugs.
 */
class Config
{
    public const string DEPLOYMENT_PATH = 'mage_obsidian/showcase_enabled';

    private ?bool $enabled = null;

    public function __construct(private readonly DeploymentConfig $deploymentConfig)
    {
    }

    public function isEnabled(): bool
    {
        return $this->enabled ??= (bool)$this->deploymentConfig->get(self::DEPLOYMENT_PATH, false);
    }
}
