<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace MageObsidian\Monorepo;

use DOMDocument;
use DOMXPath;
use RuntimeException;

final class UncacheableHandles
{
    private const array CORE_VENDORS = ['magento', 'mage-os'];
    private const array LAYOUT_AREAS = ['frontend', 'base'];
    private const array RELEASE_PACKAGES = [
        'magento/product-community-edition',
        'magento/product-enterprise-edition',
        'mage-os/product-community-edition',
        'magento/framework',
    ];

    public static function core(string $magentoRoot): array
    {
        $patterns = [];
        foreach (self::CORE_VENDORS as $vendor) {
            foreach (self::LAYOUT_AREAS as $area) {
                $patterns[] = sprintf('%s/vendor/%s/module-*/view/%s/layout/*.xml', $magentoRoot, $vendor, $area);
            }
        }

        $handles = [];
        foreach (self::layouts($patterns) as $handle => $files) {
            foreach ($files as $file) {
                if (self::declaresUncacheableBlock(self::xpath($file))) {
                    $handles[] = $handle;
                    break;
                }
            }
        }
        sort($handles);

        return ['magento' => self::release($magentoRoot), 'handles' => $handles];
    }

    public static function violations(string $root, array $coreHandles, array $policy): array
    {
        $layouts = self::layouts([
            $root . '/packages/*/src/view/frontend/layout/*.xml',
            $root . '/packages/*/src/view/base/layout/*.xml',
            $root . '/packages/theme-base/*/layout/*.xml',
        ]);
        $marks = new MarkResolver($layouts);
        $exceptions = $policy['exceptions'] ?? [];
        $violations = [];

        foreach ($coreHandles as $handle) {
            if (isset($exceptions[$handle]) || !isset($layouts[$handle]) || $marks->isMarked($handle)) {
                continue;
            }
            $violations[] = sprintf('%s is uncacheable in core, but the storefront implements it without cacheable="false"', $handle);
        }

        foreach ($policy['required'] ?? [] as $handle => $reason) {
            if (!$marks->isMarked($handle)) {
                $violations[] = sprintf('%s must be uncacheable (%s), but the storefront does not mark it', $handle, $reason);
            }
        }

        foreach (array_keys($exceptions) as $handle) {
            $stale = match (true) {
                !in_array($handle, $coreHandles, true) => 'core no longer marks it',
                !isset($layouts[$handle]) => 'the storefront no longer implements it',
                $marks->isMarked($handle) => 'the storefront already marks it',
                default => null,
            };
            if ($stale !== null) {
                $violations[] = sprintf('%s is listed as an exception, but %s', $handle, $stale);
            }
        }

        return $violations;
    }

    public static function declaresUncacheableBlock(DOMXPath $xpath): bool
    {
        return $xpath->query('//block[@cacheable="false"]')->length > 0;
    }

    public static function xpath(string $file): DOMXPath
    {
        $document = new DOMDocument();
        if (!@$document->load($file, LIBXML_NONET)) {
            throw new RuntimeException(sprintf('%s is not valid XML', $file));
        }

        return new DOMXPath($document);
    }

    private static function layouts(array $patterns): array
    {
        $layouts = [];
        foreach ($patterns as $pattern) {
            foreach (glob($pattern) ?: [] as $file) {
                $layouts[basename($file, '.xml')][] = $file;
            }
        }
        ksort($layouts);

        return $layouts;
    }

    private static function release(string $magentoRoot): string
    {
        $installed = $magentoRoot . '/vendor/composer/installed.json';
        if (!is_file($installed)) {
            return 'unknown';
        }
        $data = json_decode((string) file_get_contents($installed), true, 512, JSON_THROW_ON_ERROR);
        $versions = array_column($data['packages'] ?? $data, 'version', 'name');
        foreach (self::RELEASE_PACKAGES as $package) {
            if (isset($versions[$package])) {
                return sprintf('%s %s', $package, $versions[$package]);
            }
        }

        return 'unknown';
    }
}
