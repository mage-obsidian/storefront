<?php
/**
 * This file is part of the MageObsidian - Checkout project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

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
