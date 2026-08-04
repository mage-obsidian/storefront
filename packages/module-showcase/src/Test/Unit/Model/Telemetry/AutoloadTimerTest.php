<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Showcase project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Showcase\Test\Unit\Model\Telemetry;

use MageObsidian\Showcase\Model\Telemetry\AutoloadTimer;
use PHPUnit\Framework\TestCase;

class AutoloadTimerTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS[AutoloadTimer::GLOBAL_KEY]);
    }

    public function testConvertsTheAccumulatedNanosecondsToMilliseconds(): void
    {
        $GLOBALS[AutoloadTimer::GLOBAL_KEY] = ['ns' => 12_500_000, 'classes' => 1840];

        $this->assertSame(12.5, (new AutoloadTimer())->milliseconds());
    }

    public function testReportsHowManyClassesWereLoaded(): void
    {
        $GLOBALS[AutoloadTimer::GLOBAL_KEY] = ['ns' => 12_500_000, 'classes' => 1840];

        $this->assertSame(1840, (new AutoloadTimer())->classes());
    }

    /**
     * The prepend script is optional and lives outside the module's autoload, so
     * on any install without it there is simply nothing to report — and claiming
     * zero milliseconds would be a lie that skews every average.
     */
    public function testReportsNothingWhenThePrependIsNotInstalled(): void
    {
        $timer = new AutoloadTimer();

        $this->assertNull($timer->milliseconds());
        $this->assertNull($timer->classes());
    }

    public function testReportsNothingWhenTheGlobalIsMalformed(): void
    {
        $GLOBALS[AutoloadTimer::GLOBAL_KEY] = 'not an array';

        $timer = new AutoloadTimer();

        $this->assertNull($timer->milliseconds());
        $this->assertNull($timer->classes());
    }

    /**
     * The prepend cannot use this constant — it runs before any autoloader is
     * registered, so touching the class there is a fatal on every request. It
     * hard-codes the literal instead, and nothing but this test stops the two
     * from drifting apart into a metric that silently never appears.
     */
    public function testThePrependWritesToTheKeyThisClassReads(): void
    {
        $prepend = file_get_contents(__DIR__ . '/../../../../prepend/autoload-timer.php');

        $this->assertIsString($prepend);
        $this->assertStringContainsString("'" . AutoloadTimer::GLOBAL_KEY . "'", $prepend);
    }

    // Rounded because sub-microsecond precision is noise on a figure that only
    // exists to be compared across releases.
    public function testRoundsToMicrosecondPrecision(): void
    {
        $GLOBALS[AutoloadTimer::GLOBAL_KEY] = ['ns' => 12_345_678, 'classes' => 10];

        $this->assertSame(12.346, (new AutoloadTimer())->milliseconds());
    }
}
