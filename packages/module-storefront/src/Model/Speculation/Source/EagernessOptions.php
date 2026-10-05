<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\Model\Speculation\Source;

use Magento\Framework\Data\OptionSourceInterface;
use MageObsidian\Storefront\Model\Speculation\Eagerness;

class EagernessOptions implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return array_map(
            static fn(Eagerness $level): array => [
                'value' => $level->value,
                'label' => __($level->label()),
            ],
            Eagerness::cases()
        );
    }
}
