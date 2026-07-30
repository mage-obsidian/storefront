<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

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
