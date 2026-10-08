<?php
/**
 * This file is part of the MageObsidian - InventoryInStorePickup project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

$autoloadCandidates = [
    __DIR__ . '/../../../../../autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
];

foreach ($autoloadCandidates as $autoload) {
    if (is_file($autoload)) {
        require $autoload;
        break;
    }
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'MageObsidian\\InventoryInStorePickup\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/../../' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
