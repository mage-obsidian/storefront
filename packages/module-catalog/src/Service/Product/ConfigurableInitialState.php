<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Catalog\Service\Product;

/**
 * The swatch groups a configurable buy box shows before anything is selected.
 *
 * This is the server side of a hydrating island: the PDP template renders these
 * groups so the buy box paints with the document, and the ProductForm component
 * then adopts that markup instead of replacing it. The rules below therefore
 * mirror the component's own `swatchOf`/`isAvailable` — they are one behaviour
 * with two implementations, and the hydration warnings in developer mode are
 * what catches them drifting apart.
 *
 * Pure (no Magento dependencies) so those rules are unit-testable in isolation.
 */
class ConfigurableInitialState
{
    /**
     * Build the groups from core's assembled configurable + swatch JSON.
     *
     * @param string $configJson Output of the configurable renderer's getJsonConfig().
     * @param string $swatchJson Output of getJsonSwatchConfig(), or an empty map.
     *
     * @return array<int, array{id: string, label: string, options: array<int, array{
     *     id: string, label: string, kind: string, value: string, available: bool
     * }>}>
     */
    public function build(string $configJson, string $swatchJson = '{}'): array
    {
        $config = $this->decode($configJson);
        $swatches = $this->decode($swatchJson);
        $index = $config['index'] ?? [];

        $attributes = array_values($config['attributes'] ?? []);
        usort($attributes, static fn(array $a, array $b): int => ((int)($a['position'] ?? 0)) <=> ((int)($b['position'] ?? 0)));

        $groups = [];
        foreach ($attributes as $attribute) {
            $attributeId = (string)($attribute['id'] ?? '');
            $options = [];
            foreach ($attribute['options'] ?? [] as $option) {
                $optionId = (string)($option['id'] ?? '');
                $swatch = $this->swatchOf($swatches, $attributeId, $optionId);
                $options[] = [
                    'id' => $optionId,
                    'label' => (string)($option['label'] ?? ''),
                    'kind' => $swatch['kind'],
                    'value' => $swatch['value'],
                    'available' => $this->isAvailable($index, $attributeId, $optionId),
                ];
            }

            $groups[] = [
                'id' => $attributeId,
                'label' => (string)($attribute['label'] ?? ''),
                'options' => $options,
            ];
        }

        return $groups;
    }

    /**
     * Whether any variant carries this option, so the swatch is not greyed out.
     *
     * @param array $index Variant id => attribute id => option id.
     * @param string $attributeId
     * @param string $optionId
     *
     * @return bool
     */
    private function isAvailable(array $index, string $attributeId, string $optionId): bool
    {
        foreach ($index as $variant) {
            if (isset($variant[$attributeId]) && (string)$variant[$attributeId] === $optionId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Classify a swatch the way the component does: a value starting with "#" is
     * a colour, one containing "/" is an image path, anything else renders as
     * its text label.
     *
     * @param array $swatches
     * @param string $attributeId
     * @param string $optionId
     *
     * @return array{kind: string, value: string}
     */
    private function swatchOf(array $swatches, string $attributeId, string $optionId): array
    {
        $value = (string)($swatches[$attributeId][$optionId]['value'] ?? '');

        if (str_starts_with($value, '#')) {
            return ['kind' => 'color', 'value' => $value];
        }
        if (str_contains($value, '/')) {
            return ['kind' => 'image', 'value' => $value];
        }

        return ['kind' => 'text', 'value' => ''];
    }

    /**
     * @param string $json
     *
     * @return array
     */
    private function decode(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
