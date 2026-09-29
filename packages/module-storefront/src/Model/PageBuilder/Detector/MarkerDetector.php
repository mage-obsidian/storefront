<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

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
