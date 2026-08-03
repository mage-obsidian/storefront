<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Showcase project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Showcase\Model;

enum FeatureType: string
{
    case Flag = 'flag';

    case Choice = 'choice';

    public const string FLAG_ON = '1';

    public const string FLAG_OFF = '0';
}
