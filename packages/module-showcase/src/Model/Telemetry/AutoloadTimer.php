<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Showcase project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Showcase\Model\Telemetry;

/**
 * Reads what the autoload prepend accumulated during this request.
 *
 * The measurement cannot be taken from here: class loading is most of what the
 * bootstrap does, and by the time object manager exists it has already happened.
 * So the timing is collected by `prepend/autoload-timer.php`, which runs through
 * `auto_prepend_file` before anything else, and left in a global for this class
 * to pick up once there is somewhere to report it to.
 */
class AutoloadTimer
{
    public const string GLOBAL_KEY = 'mage_obsidian_autoload';

    private const int NS_PER_MS = 1_000_000;

    private const int PRECISION = 3;

    public function milliseconds(): ?float
    {
        $nanoseconds = $this->reading('ns');

        return $nanoseconds === null ? null : round($nanoseconds / self::NS_PER_MS, self::PRECISION);
    }

    public function classes(): ?int
    {
        return $this->reading('classes');
    }

    private function reading(string $key): ?int
    {
        // The prepend runs before any container exists, so a superglobal is the
        // only channel that reaches this far. No request input is involved.
        // phpcs:ignore Magento2.Security.Superglobal
        $collected = $GLOBALS[self::GLOBAL_KEY] ?? null;
        if (!is_array($collected) || !isset($collected[$key]) || !is_int($collected[$key])) {
            return null;
        }

        return $collected[$key];
    }
}
