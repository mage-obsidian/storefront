<?php
/**
 * This file is part of the MageObsidian - Storefront project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Storefront\Model\Seo;

use MageObsidian\Storefront\Model\Config\SeoConfig;

class CanonicalUrl
{
    public function __construct(
        private readonly EmittedCanonical $emitted,
        private readonly CanonicalUrlBuilder $builder,
        private readonly SeoConfig $config
    ) {
    }

    private ?string $emittedUrl = null;

    private ?string $builtUrl = null;

    public function getOwn(): string
    {
        if (!$this->config->isCanonicalEnabled() || $this->findEmitted() !== '') {
            return '';
        }

        return $this->build();
    }

    public function resolve(): string
    {
        $existing = $this->findEmitted();
        if ($existing !== '') {
            return $existing;
        }

        return $this->build();
    }

    private function findEmitted(): string
    {
        return $this->emittedUrl ??= $this->emitted->find();
    }

    private function build(): string
    {
        return $this->builtUrl ??= $this->builder->build();
    }
}
