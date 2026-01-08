<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Catalog project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Catalog\ViewModel;

use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Swatches\Block\LayeredNavigation\RenderLayered;
use Magento\Swatches\Helper\Data as SwatchHelper;
use Throwable;

/**
 * Swatch support for the layered-navigation sidebar. Lets the Twig decide,
 * per filter, whether the attribute is a swatch and — when it is — hands it the
 * native render data (option labels/urls/counts merged with the swatch type and
 * value). Visual-color swatches carry a hex value the sidebar paints; everything
 * else falls back to a text chip.
 */
class LayeredSwatch implements ArgumentInterface
{
    /**
     * @param SwatchHelper $swatchHelper
     * @param RenderLayered $renderer
     */
    public function __construct(
        private readonly SwatchHelper $swatchHelper,
        private readonly RenderLayered $renderer
    ) {
    }

    /**
     * Whether the filter's attribute is rendered as a swatch.
     *
     * @param AbstractFilter $filter
     * @return bool
     */
    public function isSwatch(AbstractFilter $filter): bool
    {
        try {
            $attribute = $filter->getAttributeModel();

            return $this->swatchHelper->isSwatchAttribute($attribute);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Normalized swatch options for a filter.
     *
     * Each entry is {label, link, color}: color is a validated hex for visual-color
     * swatches (so the Twig can paint it safely) and null otherwise — text/image
     * swatches fall back to a label chip. Empty when it cannot resolve.
     *
     * @param AbstractFilter $filter
     * @return array<int, array{label: string, link: string, color: string|null}>
     */
    public function getData(AbstractFilter $filter): array
    {
        try {
            $data = $this->renderer->setSwatchFilter($filter)->getSwatchData();
            $swatches = $data['swatches'] ?? [];
            $items = [];
            foreach ($data['options'] ?? [] as $optionId => $option) {
                $swatch = $swatches[$optionId] ?? [];
                $items[] = [
                    'label' => (string)($option['label'] ?? ''),
                    'link' => (string)($option['link'] ?? ''),
                    'color' => $this->colorOf($swatch),
                ];
            }

            return $items;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Validated hex for a visual-color swatch, or null.
     *
     * @param array $swatch
     * @return string|null
     */
    private function colorOf(array $swatch): ?string
    {
        $value = (string)($swatch['value'] ?? '');
        if ((int)($swatch['type'] ?? 0) === 1 && preg_match('/^#[0-9a-fA-F]{3,8}$/', $value)) {
            return $value;
        }

        return null;
    }
}
