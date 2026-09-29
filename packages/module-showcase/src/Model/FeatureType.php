<?php
/**
 * This file is part of the MageObsidian - Showcase project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Showcase\Model;

enum FeatureType: string
{
    case Flag = 'flag';

    case Choice = 'choice';

    public const string FLAG_ON = '1';

    public const string FLAG_OFF = '0';
}
