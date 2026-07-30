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
use MageObsidian\Storefront\Model\Speculation\Mode;

class ModeOptions implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return array_map(
            static fn(Mode $mode): array => ['value' => $mode->value, 'label' => __($mode->label())],
            Mode::cases()
        );
    }
}
