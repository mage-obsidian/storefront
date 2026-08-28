<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Checkout\Model\Payment;

use MageObsidian\Checkout\Api\MethodDataProviderInterface;
use Throwable;

class MethodData
{
    public function __construct(private readonly array $providers = [])
    {
    }

    public function getData(): array
    {
        $data = [];

        foreach ($this->providers as $provider) {
            if (!$provider instanceof MethodDataProviderInterface) {
                continue;
            }
            try {
                $contributed = $provider->getData();
            } catch (Throwable) {
                continue;
            }
            foreach ($contributed as $code => $entry) {
                $code = (string)$code;
                if ($code === '' || !is_array($entry)) {
                    continue;
                }
                $data[$code] = array_merge($data[$code] ?? [], $entry);
            }
        }

        return $data;
    }
}
