<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\Model\Seo;

enum ManifestDisplay: string
{
    case Browser = 'browser';
    case MinimalUi = 'minimal-ui';
    case Standalone = 'standalone';
    case Fullscreen = 'fullscreen';

    public static function fromConfig(?string $value): self
    {
        return self::tryFrom((string)$value) ?? self::Standalone;
    }

    public function label(): string
    {
        return match ($this) {
            self::Browser => 'Browser',
            self::MinimalUi => 'Minimal UI',
            self::Standalone => 'Standalone',
            self::Fullscreen => 'Fullscreen',
        };
    }
}
