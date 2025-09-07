<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - Review project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

namespace MageObsidian\Review\ViewModel;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Review\Model\Review;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

/**
 * Approved product reviews for the PDP, consumed from Twig as
 * `block.getReviews().getItems()`. The rating summary and the submit form reuse
 * core blocks (ReviewRenderer / Block\Form) as data sources; this ViewModel owns
 * the approved-review list, normalized for our Twig + the review JSON-LD.
 *
 * Each item carries an average star percentage (0–100) computed from its rating
 * votes, so the template can draw stars without re-querying.
 */
class ProductReviews implements ArgumentInterface
{
    /** @var array<int, array{title: string, detail: string, nickname: string, created_at: string, percent: int}>|null */
    private ?array $items = null;

    /**
     * @param Registry $registry
     * @param CollectionFactory $collectionFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly Registry $registry,
        private readonly CollectionFactory $collectionFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Approved reviews for the current product, newest first.
     *
     * @return array<int, array{title: string, detail: string, nickname: string, created_at: string, percent: int}>
     */
    public function getItems(): array
    {
        if ($this->items !== null) {
            return $this->items;
        }

        $this->items = [];
        try {
            $product = $this->registry->registry('current_product');
            if (!$product) {
                return $this->items;
            }
            $collection = $this->collectionFactory->create()
                ->addStoreFilter((int)$this->storeManager->getStore()->getId())
                ->addStatusFilter(Review::STATUS_APPROVED)
                ->addEntityFilter('product', (int)$product->getId())
                ->setDateOrder()
                ->addRateVotes();

            foreach ($collection as $review) {
                $this->items[] = [
                    'title' => (string)$review->getTitle(),
                    'detail' => (string)$review->getDetail(),
                    'nickname' => (string)$review->getNickname(),
                    'created_at' => (string)$review->getCreatedAt(),
                    'percent' => $this->averagePercent($review),
                ];
            }
        } catch (Throwable) {
            $this->items = [];
        }

        return $this->items;
    }

    /**
     * Whether the current product has at least one approved review.
     *
     * @return bool
     */
    public function hasReviews(): bool
    {
        return $this->getItems() !== [];
    }

    /**
     * Number of approved reviews.
     *
     * @return int
     */
    public function getCount(): int
    {
        return count($this->getItems());
    }

    /**
     * Current product name, for the aggregateRating JSON-LD itemReviewed.
     *
     * @return string
     */
    public function getProductName(): string
    {
        $product = $this->registry->registry('current_product');

        return $product ? (string)$product->getName() : '';
    }

    /**
     * Current product URL for the JSON-LD node.
     *
     * Used as the node URL so crawlers merge it with the engine's Product node
     * for the same page.
     *
     * @return string
     */
    public function getProductUrl(): string
    {
        $product = $this->registry->registry('current_product');

        return $product ? (string)$product->getProductUrl() : '';
    }

    /**
     * Average rating on a 0–5 scale (one decimal), for display and JSON-LD.
     *
     * @return float
     */
    public function getAverageStars(): float
    {
        return round($this->getAveragePercent() / 20, 1);
    }

    /**
     * Average star percentage (0–100) across all approved reviews.
     *
     * Feeds the aggregateRating JSON-LD and the summary fallback.
     *
     * @return int
     */
    public function getAveragePercent(): int
    {
        $items = $this->getItems();
        if ($items === []) {
            return 0;
        }
        $sum = array_sum(array_column($items, 'percent'));

        return (int)round($sum / count($items));
    }

    /**
     * Average of a review's rating votes as a 0–100 percentage.
     *
     * @param object $review
     * @return int
     */
    private function averagePercent(object $review): int
    {
        $votes = $review->getRatingVotes();
        if (!$votes || count($votes) === 0) {
            return 0;
        }
        $sum = 0;
        foreach ($votes as $vote) {
            $sum += (int)$vote->getPercent();
        }

        return (int)round($sum / count($votes));
    }
}
