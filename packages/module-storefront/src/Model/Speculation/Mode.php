<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

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
