<?php
/**
 * This file is part of the MageObsidian - Persistent project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Persistent\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Persistent\Helper\Data as PersistentHelper;

class Greeting implements ArgumentInterface
{
    public function __construct(
        private readonly PersistentHelper $persistentHelper
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool)$this->persistentHelper->isEnabled();
    }
}
