<?php
declare(strict_types=1);

/**
 * Standalone unit bootstrap for module-review.
 *
 * When the Composer autoloader is present (module installed inside a Magento
 * root) it is used; otherwise a PSR-4 fallback loads this module's sources from
 * `src/`. Tests needing the Magento framework run inside a Magento root and skip
 * when it is absent.
 */

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
    $prefix = 'MageObsidian\\Review\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/../../' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
