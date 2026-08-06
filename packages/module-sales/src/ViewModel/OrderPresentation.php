<?php
/**
 * This file is part of the MageObsidian - Sales project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\Sales\ViewModel;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Item\CollectionFactory as OrderItemCollectionFactory;

/**
 * Everything the account templates need to *show* an order: its product images,
 * the semantic tone of its status chip, and its fulfilment steps.
 */
class OrderPresentation implements ArgumentInterface
{
    /**
     * Keyed by state, not status: merchants add their own statuses, but the
     * states are fixed, so a custom "awaiting_stock" status still lands on the
     * right colour instead of falling through to grey.
     */
    private const STATE_TONES = [
        Order::STATE_NEW => 'neutral',
        Order::STATE_PENDING_PAYMENT => 'neutral',
        Order::STATE_PROCESSING => 'progress',
        Order::STATE_PAYMENT_REVIEW => 'progress',
        Order::STATE_COMPLETE => 'good',
        Order::STATE_HOLDED => 'warn',
        Order::STATE_CANCELED => 'dead',
        Order::STATE_CLOSED => 'dead',
    ];

    private const TRACK_KEYS = ['received', 'processing', 'shipped', 'delivered'];

    public function __construct(
        private readonly OrderItemCollectionFactory $itemCollectionFactory,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly ImageHelper $imageHelper
    ) {
    }

    /**
     * Chip tone for an order's status. Unknown states read as neutral rather
     * than as an error — a status chip is not worth a broken page.
     */
    public function getStateTone(OrderInterface $order): string
    {
        return self::STATE_TONES[(string)$order->getState()] ?? 'neutral';
    }

    /**
     * Product thumbnails for a whole page of orders, as
     * [orderId => ['thumbs' => [{url,width,height,label}], 'total' => int]].
     *
     * Two queries for the entire page — one for the items, one for the products —
     * instead of the two-per-order that walking $order->getAllVisibleItems() and
     * $item->getProduct() would cost. `total` is the real line count, so the
     * template's overflow badge stays honest when the list is capped.
     *
     * @param array<int, OrderInterface> $orders
     * @return array<int, array{thumbs: array<int, array>, total: int}>
     */
    public function getThumbnails(array $orders, int $limit = 4): array
    {
        $orderIds = array_values(array_filter(array_map(
            static fn (OrderInterface $order): int => (int)$order->getEntityId(),
            $orders
        )));

        if ($orderIds === []) {
            return [];
        }

        $items = $this->itemCollectionFactory->create()
            ->addFieldToFilter('order_id', ['in' => $orderIds])
            ->addFieldToFilter('parent_item_id', ['null' => true]);

        $productIdsByOrder = [];
        foreach ($items as $item) {
            $productIdsByOrder[(int)$item->getOrderId()][] = (int)$item->getProductId();
        }

        if ($productIdsByOrder === []) {
            return [];
        }

        $products = $this->loadProducts(array_unique(array_merge(...array_values($productIdsByOrder))));

        $result = [];
        foreach ($productIdsByOrder as $orderId => $productIds) {
            $thumbs = [];
            foreach (array_slice($productIds, 0, $limit) as $productId) {
                $product = $products[$productId] ?? null;
                if ($product !== null) {
                    $thumbs[] = $this->thumbnailOf($product);
                }
            }
            $result[$orderId] = ['thumbs' => $thumbs, 'total' => count($productIds)];
        }

        return $result;
    }

    /**
     * Fulfilment steps as [{key, state}] where state is done|current|pending.
     * The labels are left to Twig — they are translatable copy, not data.
     *
     * Single-order use only (the detail page, the dashboard's latest order): it
     * reads the shipments collection, which is a query per order.
     *
     * @return array<int, array{key: string, state: string}>
     */
    public function getTrack(OrderInterface $order): array
    {
        $reached = $this->reachedStepIndex($order);

        return array_map(
            static fn (string $key, int $index): array => [
                'key' => $key,
                'state' => match (true) {
                    $index < $reached => 'done',
                    $index === $reached => 'current',
                    default => 'pending',
                },
            ],
            self::TRACK_KEYS,
            array_keys(self::TRACK_KEYS)
        );
    }

    /**
     * Whether the order still moves through the track at all. A canceled or
     * closed order has no progress to show — only a chip.
     */
    public function isTrackable(OrderInterface $order): bool
    {
        return !in_array(
            (string)$order->getState(),
            [Order::STATE_CANCELED, Order::STATE_CLOSED],
            true
        );
    }

    private function reachedStepIndex(OrderInterface $order): int
    {
        if ((string)$order->getState() === Order::STATE_COMPLETE) {
            return 3;
        }

        if ($order instanceof Order && $order->hasShipments()) {
            return 2;
        }

        return (string)$order->getState() === Order::STATE_PROCESSING ? 1 : 0;
    }

    /**
     * @param array<int, int> $productIds
     * @return array<int, \Magento\Catalog\Api\Data\ProductInterface>
     */
    private function loadProducts(array $productIds): array
    {
        $collection = $this->productCollectionFactory->create()
            ->addAttributeToSelect(['name', 'thumbnail', 'small_image', 'image'])
            ->addIdFilter($productIds);

        $byId = [];
        foreach ($collection as $product) {
            $byId[(int)$product->getId()] = $product;
        }

        return $byId;
    }

    /**
     * @return array{url: string, width: int, height: int, label: string}
     */
    private function thumbnailOf(\Magento\Catalog\Api\Data\ProductInterface $product): array
    {
        $helper = $this->imageHelper->init($product, 'category_page_grid');

        return [
            'url' => (string)$helper->getUrl(),
            'width' => (int)$helper->getWidth(),
            'height' => (int)$helper->getHeight(),
            'label' => (string)($helper->getLabel() ?: $product->getName()),
        ];
    }
}
