<?php
declare(strict_types=1);

/**
 * Standalone unit bootstrap for module-checkout.
 *
 * Mirrors the storefront module's bootstrap. When the Composer autoloader is
 * present (module installed inside a Magento root) it is used and takes
 * precedence; otherwise a PSR-4 fallback loads this module's sources directly
 * from `src/`. Tests that need the Magento framework run inside a Magento root
 * (they skip when it is absent).
 */

$autoloadCandidates = [
    __DIR__ . '/../../../../../autoload.php',           // vendor/mage-obsidian/module-checkout/src/Test/Unit -> vendor/autoload.php
    __DIR__ . '/../../../vendor/autoload.php',           // standalone repo with its own vendor/
];

foreach ($autoloadCandidates as $autoload) {
    if (is_file($autoload)) {
        require $autoload;
        break;
    }
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'MageObsidian\\Checkout\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/../../' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
