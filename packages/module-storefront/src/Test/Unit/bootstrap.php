<?php
declare(strict_types=1);

/**
 * Standalone unit bootstrap for module-storefront.
 *
 * Mirrors the twig module's bootstrap. When the Composer autoloader is present
 * (module installed inside a Magento root) it is used and takes precedence;
 * otherwise a PSR-4 fallback loads this module's sources directly from `src/`.
 * Tests that need Twig or the Magento framework run inside a Magento root (see
 * phpunit.ci.xml, which excludes them from the standalone suite).
 */

$autoloadCandidates = [
    __DIR__ . '/../../../../../autoload.php',           // vendor/mage-obsidian/module-storefront/src/Test/Unit -> vendor/autoload.php
    __DIR__ . '/../../../vendor/autoload.php',           // standalone repo with its own vendor/
];

foreach ($autoloadCandidates as $autoload) {
    if (is_file($autoload)) {
        require $autoload;
        break;
    }
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'MageObsidian\\Storefront\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/../../' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
