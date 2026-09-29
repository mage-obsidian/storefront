<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\Model\Speculation;

/**
 * Prerender runs the whole page — islands, section load, analytics — for a
 * destination the visitor may never open, so it stays opt-in behind config.
 */
enum Mode: string
{
    case Prefetch = 'prefetch';
    case Prerender = 'prerender';

    /**
     * @param string|null $value
     * @return self
     */
    public static function fromConfig(?string $value): self
    {
        return self::tryFrom((string)$value) ?? self::Prefetch;
    }

    /**
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Prefetch => 'Prefetch',
            self::Prerender => 'Prerender',
        };
    }
}
