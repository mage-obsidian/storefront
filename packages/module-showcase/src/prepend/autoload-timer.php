<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Showcase project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 *
 * Measures what class loading costs, for `MageObsidian\Showcase\Model\Telemetry\AutoloadTimer`
 * to report. Install by pointing PHP's `auto_prepend_file` at this file:
 *
 *     auto_prepend_file = ".../vendor/mage-obsidian/module-showcase/src/prepend/autoload-timer.php"
 *
 * Only one `auto_prepend_file` can be set, so if the environment already has one,
 * `require` this from there instead.
 *
 * It has to run this early because class loading IS most of what the bootstrap
 * does — by the time any Magento code could hook it, the expensive part is over.
 *
 * Note it loads Composer's autoloader itself rather than waiting to be called.
 * Registering a listener does not work: Magento's bootstrap registers Composer
 * with `register(true)`, which puts it ahead of anything this file registered,
 * so it resolves every class and the listener never runs — it measured a flat
 * zero. Requiring `autoload.php` here returns the same ClassLoader instance the
 * bootstrap will get, and it can be swapped for a timed one before any Magento
 * class exists.
 *
 * Everything here runs on every single request before anything else, so it stays
 * defensive: any surprise means no measurement, never a broken page.
 */

// phpcs:disable Magento2.Security.IncludeFile, Magento2.Security.Superglobal
// Both are unavoidable here and neither is the risk the sniffs guard against.
// This file runs before any autoloader or object manager exists, so requiring
// Composer's autoloader by a path derived from __DIR__ is the only way to reach
// it, and a superglobal is the only channel that survives until Magento code can
// read the measurement. No request input is involved in either.

(static function (): void {
    // The literal is deliberate: referencing AutoloadTimer::GLOBAL_KEY would try
    // to autoload a class before any autoloader exists, which is a fatal on every
    // request. AutoloadTimerTest pins the two together.
    $key = 'mage_obsidian_autoload';

    // Walk up from this file and from the working directory: installed normally
    // the module sits inside vendor/ (so autoload.php is an ancestor), but it can
    // also be mounted outside the Magento root, where only the cwd leads to it.
    $autoload = null;
    foreach ([__DIR__, getcwd() ?: __DIR__] as $from) {
        for ($directory = $from, $depth = 0; $depth < 8; $depth++) {
            foreach ([$directory . '/autoload.php', $directory . '/vendor/autoload.php'] as $candidate) {
                if (is_file($candidate)) {
                    $autoload = $candidate;
                    break 3;
                }
            }
            $parent = dirname($directory);
            if ($parent === $directory) {
                break;
            }
            $directory = $parent;
        }
    }

    if ($autoload === null) {
        return;
    }

    $loader = require_once $autoload;
    if (!$loader instanceof \Composer\Autoload\ClassLoader) {
        return;
    }

    $GLOBALS[$key] = ['ns' => 0, 'classes' => 0];

    spl_autoload_unregister([$loader, 'loadClass']);
    spl_autoload_register(
        static function (string $class) use ($loader, $key): void {
            $started = hrtime(true);
            $loader->loadClass($class);
            $GLOBALS[$key]['ns'] += hrtime(true) - $started;
            $GLOBALS[$key]['classes']++;
        },
        true,
        true
    );
})();
