<?php
/**
 * This file is part of the MageObsidian - Persistent project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\Persistent\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Persistent\Helper\Data as PersistentHelper;

/**
 * "Remember Me" checkbox state for the sign-in / registration forms.
 *
 * Injected into the form blocks so Magento_Customer stays unaware of Persistent.
 * `isEnabled()` mirrors the native gate (persistent + remember-me both on);
 * `isCheckedByDefault()` reflects the configured default.
 */
class Remember implements ArgumentInterface
{
    /**
     * @param PersistentHelper $persistentHelper
     */
    public function __construct(
        private readonly PersistentHelper $persistentHelper
    ) {
    }

    /**
     * Whether the remember-me checkbox should be offered.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return (bool)$this->persistentHelper->isEnabled()
            && (bool)$this->persistentHelper->isRememberMeEnabled();
    }

    /**
     * Whether the checkbox is ticked by default.
     *
     * @return bool
     */
    public function isCheckedByDefault(): bool
    {
        return (bool)$this->persistentHelper->isRememberMeCheckedDefault();
    }
}
