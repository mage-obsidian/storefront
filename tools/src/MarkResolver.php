<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace MageObsidian\Monorepo;

final class MarkResolver
{
    private array $resolved = [];

    public function __construct(private readonly array $layouts)
    {
    }

    public function isMarked(string $handle): bool
    {
        return $this->resolve($handle, []);
    }

    private function resolve(string $handle, array $visiting): bool
    {
        if (isset($this->resolved[$handle])) {
            return $this->resolved[$handle];
        }
        if (isset($visiting[$handle]) || !isset($this->layouts[$handle])) {
            return false;
        }
        $visiting[$handle] = true;

        $updates = [];
        foreach ($this->layouts[$handle] as $file) {
            $xpath = UncacheableHandles::xpath($file);
            if (UncacheableHandles::declaresUncacheableBlock($xpath)) {
                return $this->resolved[$handle] = true;
            }
            foreach ($xpath->query('//update/@handle') as $attribute) {
                $updates[] = (string) $attribute->nodeValue;
            }
        }

        foreach ($updates as $update) {
            if ($this->resolve($update, $visiting)) {
                return $this->resolved[$handle] = true;
            }
        }

        return false;
    }
}
