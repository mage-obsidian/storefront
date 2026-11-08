<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace MageObsidian\Monorepo\Test;

use MageObsidian\Monorepo\UncacheableHandles;
use PHPUnit\Framework\TestCase;

final class StorefrontUncacheableHandlesTest extends TestCase
{
    public function testEveryPrivatePageTheStorefrontShipsStaysOutOfThePageCache(): void
    {
        $root = dirname(__DIR__, 2);
        $core = $this->json($root . '/tools/data/core-uncacheable-handles.json');
        $policy = $this->json($root . '/tools/data/uncacheable-handle-policy.json');

        self::assertSame([], UncacheableHandles::violations($root, $core['handles'], $policy));
    }

    private function json(string $path): array
    {
        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }
}
