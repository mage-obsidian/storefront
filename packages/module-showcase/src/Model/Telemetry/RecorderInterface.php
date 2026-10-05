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
 * Where a request's feature set is sent. An interface because the agent behind
 * it is a PHP extension that is absent on most installs and cannot be stood up
 * in a test.
 */
interface RecorderInterface
{
    /**
     * Numbers must stay numbers: the agent types an attribute from the value it
     * is handed, and on a string one `average()` is null in NRQL unless every
     * query remembers `numeric()`.
     */
    public function record(string $name, string|int|float $value): void;
}
