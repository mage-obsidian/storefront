<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Storefront\Model\PageBuilder\Detector;

class MarkerDetector implements DetectorInterface
{
    public function __construct(
        private readonly string $marker,
        private readonly string $module
    ) {
    }

    public function matches(string $html): bool
    {
        return $this->marker !== '' && str_contains($html, $this->marker);
    }

    public function getModule(): string
    {
        return $this->module;
    }
}
