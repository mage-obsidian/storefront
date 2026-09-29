<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\Model\Seo\Source;

use Magento\Framework\Data\OptionSourceInterface;
use MageObsidian\Storefront\Model\Seo\ManifestDisplay;

class ManifestDisplayOptions implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return array_map(
            static fn(ManifestDisplay $display): array => [
                'value' => $display->value,
                'label' => __($display->label()),
            ],
            ManifestDisplay::cases()
        );
    }
}
