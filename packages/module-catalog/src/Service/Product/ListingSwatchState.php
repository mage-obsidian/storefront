<?php
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * SPDX-FileCopyrightText: 2024 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Catalog\Service\Product;

class ListingSwatchState
{
    public function __construct(private readonly ConfigurableInitialState $initialState)
    {
    }

    public function build(string $configJson, string $swatchJson, callable $variantImage, int $limit): array
    {
        $swatches = $this->decode($swatchJson);
        $variants = $this->decode($configJson)['index'] ?? [];

        $groups = [];
        foreach ($this->initialState->build($configJson, $swatchJson) as $group) {
            if (!$this->hasSwatches($swatches[$group['id']] ?? null)) {
                continue;
            }

            $options = array_values(array_filter(
                $group['options'],
                static fn(array $option): bool => $option['available']
            ));
            $groups[] = ['source' => $group, 'options' => $options];
        }

        $pictured = $this->picturedGroup($groups);

        $built = [];
        foreach ($groups as $position => $group) {
            $options = [];
            foreach ($group['options'] as $option) {
                $options[] = [
                    'id' => $option['id'],
                    'label' => $option['label'],
                    'kind' => $option['kind'],
                    'value' => $option['value'],
                    'image' => $position === $pictured
                        ? $this->imageOf($variants, $group['source']['id'], $option['id'], $variantImage)
                        : null,
                ];
            }

            $built[] = [
                'id' => $group['source']['id'],
                'code' => $group['source']['code'],
                'label' => $group['source']['label'],
                'hidden' => max(0, count($options) - $limit),
                'options' => array_slice($options, 0, $limit),
            ];
        }

        return $built;
    }

    public function purchase(string $configJson, array $groups, callable $formatPrice): array
    {
        $config = $this->decode($configJson);
        if ($groups === [] || count($groups) !== count($config['attributes'] ?? [])) {
            return [];
        }

        $attributeIds = array_column($groups, 'id');
        $variants = [];
        foreach ($config['index'] ?? [] as $variantId => $selection) {
            $amount = $config['optionPrices'][$variantId]['finalPrice']['amount'] ?? null;
            $options = array_map(static fn(string $id): string => (string)($selection[$id] ?? ''), $attributeIds);
            if (!is_numeric($amount) || in_array('', $options, true)) {
                continue;
            }
            $variants[implode(',', $options)] = ['price' => $formatPrice((float)$amount), 'id' => (int)$variantId];
        }

        return $variants === [] ? [] : ['attributes' => array_column($groups, 'code'), 'variants' => $variants];
    }

    private function picturedGroup(array $groups): int
    {
        foreach ($groups as $position => $group) {
            foreach ($group['options'] as $option) {
                if ($option['kind'] !== 'text') {
                    return $position;
                }
            }
        }

        return 0;
    }

    private function hasSwatches(mixed $entries): bool
    {
        if (!is_array($entries)) {
            return false;
        }
        unset($entries['additional_data']);

        return $entries !== [];
    }

    private function imageOf(array $variants, string $attributeId, string $optionId, callable $variantImage): ?array
    {
        foreach ($variants as $variantId => $selection) {
            if ((string)($selection[$attributeId] ?? '') !== $optionId) {
                continue;
            }
            $image = $variantImage((string)$variantId);
            if ($image !== null) {
                return $image;
            }
        }

        return null;
    }

    private function decode(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
