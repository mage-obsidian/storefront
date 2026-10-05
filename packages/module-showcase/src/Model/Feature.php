<?php
/**
 * This file is part of the MageObsidian - Showcase project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Showcase\Model;

/**
 * One switchable feature: the config path it stands for, and the values a
 * visitor is allowed to put there.
 */
class Feature
{
    /**
     * @param array<string, string> $options value => label, for a Choice
     */
    public function __construct(
        public readonly string $key,
        public readonly string $path,
        public readonly string $label,
        public readonly FeatureType $type,
        public readonly string $description = '',
        public readonly ?string $module = null,
        public readonly array $options = []
    ) {
    }

    public function accepts(string $value): bool
    {
        return match ($this->type) {
            FeatureType::Flag => $value === FeatureType::FLAG_ON || $value === FeatureType::FLAG_OFF,
            FeatureType::Choice => isset($this->options[$value]),
        };
    }

    /**
     * @return array<string, string>
     */
    public function choices(): array
    {
        return $this->type === FeatureType::Choice
            ? $this->options
            : [FeatureType::FLAG_ON => (string)__('On'), FeatureType::FLAG_OFF => (string)__('Off')];
    }
}
