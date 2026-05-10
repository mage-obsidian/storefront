<?php
/**
 * This file is part of the MageObsidian - Sales project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\Sales\ViewModel;

use Magento\Framework\DataObject;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Throwable;

/**
 * Per-item detail for the order/invoice/credit-memo/shipment Twig tables.
 *
 * MageObsidian suppresses the native sales item renderers, so the modern order
 * templates only had product name + price. This ViewModel restores the option
 * lines the legacy renderers showed — custom options, configurable attributes
 * and additional options (mirroring Sales\Block\Order\Item\Renderer\Default's
 * getItemOptions) — plus bundle selection children. Accepts either an order item
 * or an invoice/shipment/credit-memo item (the latter wrap the order item).
 */
class ItemOptions implements ArgumentInterface
{
    /**
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * Labelled option lines for an item ([{label, value}]), empty on any failure.
     *
     * @param DataObject $item
     * @return array<int, array{label: string, value: string}>
     */
    public function getOptions(DataObject $item): array
    {
        $options = $this->orderItem($item)?->getProductOptions();
        if (!is_array($options)) {
            return [];
        }

        $groups = [];
        foreach (['options', 'additional_options', 'attributes_info'] as $key) {
            if (!empty($options[$key]) && is_array($options[$key])) {
                $groups[] = $options[$key];
            }
        }

        $result = [];
        foreach (array_merge([], ...$groups) as $option) {
            if (!is_array($option) || !isset($option['label'])) {
                continue;
            }
            $result[] = [
                'label' => (string)$option['label'],
                'value' => $this->normaliseValue($option['value'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * Bundle selection children for a parent item ([{name, qty, price}]).
     *
     * Children carry their quantity and price in `bundle_selection_attributes`;
     * price is returned as a float so the template formats it with the order's
     * currency (matching the other amount columns).
     *
     * @param DataObject $item
     * @return array<int, array{name: string, qty: float, price: float}>
     */
    public function getChildren(DataObject $item): array
    {
        $orderItem = $this->orderItem($item);
        if ($orderItem === null || !$orderItem->getHasChildren()) {
            return [];
        }

        $children = [];
        foreach ($orderItem->getChildrenItems() as $child) {
            // Only bundle children carry selection attributes; this skips the
            // simple child a configurable item also exposes via getChildrenItems.
            $attributes = $this->selectionAttributes($child);
            if ($attributes === []) {
                continue;
            }
            $children[] = [
                'name' => (string)$child->getName(),
                'qty' => (float)($attributes['qty'] ?? $child->getQtyOrdered()),
                'price' => (float)($attributes['price'] ?? $child->getPrice()),
            ];
        }

        return $children;
    }

    /**
     * Resolve the underlying order item (invoice/shipment/credit-memo items wrap it).
     *
     * @param DataObject $item
     * @return DataObject|null
     */
    private function orderItem(DataObject $item): ?DataObject
    {
        if (method_exists($item, 'getOrderItem')) {
            $orderItem = $item->getOrderItem();
            if ($orderItem instanceof DataObject) {
                return $orderItem;
            }
        }

        return $item;
    }

    /**
     * Decode a child item's serialized bundle selection attributes (qty, price).
     *
     * @param DataObject $child
     * @return array<string, mixed>
     */
    private function selectionAttributes(DataObject $child): array
    {
        $options = $child->getProductOptions();
        if (!is_array($options) || empty($options['bundle_selection_attributes'])) {
            return [];
        }

        try {
            $decoded = $this->serializer->unserialize($options['bundle_selection_attributes']);
        } catch (Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Flatten an option value (string / list / labelled array) to plain text.
     *
     * @param mixed $value
     * @return string
     */
    private function normaliseValue(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value['value'] ?? implode(', ', array_filter($value, 'is_scalar'));
        }

        return trim(strip_tags((string)$value));
    }
}
