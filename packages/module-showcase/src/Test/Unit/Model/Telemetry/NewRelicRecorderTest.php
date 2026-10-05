<?php
/**
 * This file is part of the MageObsidian - Showcase project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Showcase\Test\Unit\Model\Telemetry;

use MageObsidian\Showcase\Model\Telemetry\NewRelicRecorder;
use PHPUnit\Framework\TestCase;

class NewRelicRecorderTest extends TestCase
{
    /**
     * The agent is a PHP extension, so on every install that does not have it
     * loaded these functions simply do not exist. Calling one anyway is a fatal
     * error, and this recorder runs on every request.
     */
    public function testDoesNothingWhereTheAgentIsNotInstalled(): void
    {
        if (function_exists('newrelic_add_custom_parameter')) {
            $this->markTestSkipped('The New Relic agent is loaded here.');
        }

        $this->expectNotToPerformAssertions();

        (new NewRelicRecorder())->record('obsidian.store', 'classic');
    }
}
