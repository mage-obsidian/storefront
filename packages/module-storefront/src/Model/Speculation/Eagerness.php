<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\Model\Speculation;

enum Eagerness: string
{
    case Conservative = 'conservative';
    case Moderate = 'moderate';
    case Eager = 'eager';

    /**
     * @param string|null $value
     * @return self
     */
    public static function fromConfig(?string $value): self
    {
        return self::tryFrom((string)$value) ?? self::Moderate;
    }

    /**
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Conservative => 'Conservative',
            self::Moderate => 'Moderate',
            self::Eager => 'Eager',
        };
    }
}
