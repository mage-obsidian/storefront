<?php
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\Checkout\Model;

use MageObsidian\Checkout\Api\VaultTokenProviderInterface;

/**
 * Default no-op provider: the checkout shows no stored cards unless the vault
 * module overrides the preference.
 */
class NullVaultTokenProvider implements VaultTokenProviderInterface
{
    /**
     * @inheritDoc
     */
    public function getTokens(): array
    {
        return [];
    }
}
