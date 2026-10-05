<?php
/**
 * This file is part of the MageObsidian - Showcase project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Showcase\Model\Telemetry;

/**
 * Attaches the feature set to the current transaction as custom attributes,
 * which is what makes them available to facet on in NRQL.
 *
 * Custom attributes are chosen over renaming the transaction on purpose: the
 * transaction name is the axis every historical comparison is built on, and
 * splitting it by feature would make each release incomparable with the last.
 */
class NewRelicRecorder implements RecorderInterface
{
    private const string AGENT_FUNCTION = 'newrelic_add_custom_parameter';

    public function record(string $name, string|int|float $value): void
    {
        // The agent ships as a PHP extension that most installs do not load.
        if (!function_exists(self::AGENT_FUNCTION)) {
            return;
        }

        \newrelic_add_custom_parameter($name, $value);
    }
}
