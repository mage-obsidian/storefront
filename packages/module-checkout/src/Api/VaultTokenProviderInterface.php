<?php
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\Checkout\Api;

/**
 * Supplies the checkout island's stored-card list. Lives here (not in the vault
 * module) so the checkout config stays decoupled: a no-op default ships with this
 * module and MageObsidian_Vault overrides the preference when present. Without a
 * configured tokenizing gateway the list is empty and the island is unchanged.
 */
interface VaultTokenProviderInterface
{
    /**
     * The current customer's saved cards, ready for the payment step.
     *
     * @return array<int, array{publicHash: string, methodCode: string, last4: string, type: string, typeLabel: string, expiration: string}>
     */
    public function getTokens(): array;
}
