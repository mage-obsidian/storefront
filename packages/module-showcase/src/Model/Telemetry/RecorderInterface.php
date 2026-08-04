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
 * Where a request's feature set is sent. An interface because the agent behind
 * it is a PHP extension that is absent on most installs and cannot be stood up
 * in a test.
 */
interface RecorderInterface
{
    public function record(string $name, string $value): void;
}
