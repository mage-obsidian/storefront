<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\Model\PageBuilder\Detector;

interface DetectorInterface
{
    public function matches(string $html): bool;

    public function getModule(): string;
}
