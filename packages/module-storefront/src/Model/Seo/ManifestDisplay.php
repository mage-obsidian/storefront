<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

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
