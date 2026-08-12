<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Review\Plugin\SchemaOrg;

use MageObsidian\ModernFrontend\Model\SchemaOrg\CurrentPageSchemaProvider;
use MageObsidian\Review\ViewModel\ProductReviews;

/**
 * Adds the rating and the reviews to the page's single `Product` node.
 *
 * The core module emits one Product node per page and knows nothing about
 * Magento_Review, so the rating is contributed from here — the only module that
 * already depends on it. Emitting a second Product node from a template instead
 * would leave search engines choosing between two partial descriptions of the
 * same product.
 */
class AddProductRating
{
    private const int BEST_RATING = 5;
    private const int PERCENT_PER_STAR = 20;

    public function __construct(
        private readonly ProductReviews $productReviews
    ) {
    }

    /**
     * @param list<array<string,mixed>> $result
     *
     * @return list<array<string,mixed>>
     */
    public function afterGetCurrentPageNodes(
        CurrentPageSchemaProvider $subject,
        array $result
    ): array {
        if (!$this->productReviews->hasReviews()) {
            return $result;
        }

        foreach ($result as $index => $node) {
            if (($node['@type'] ?? null) !== 'Product') {
                continue;
            }
            $result[$index]['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (string)$this->productReviews->getAverageStars(),
                'reviewCount' => $this->productReviews->getCount(),
                'bestRating' => (string)self::BEST_RATING,
            ];
            $result[$index]['review'] = $this->buildReviews();
            break;
        }

        return $result;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function buildReviews(): array
    {
        $reviews = [];
        foreach ($this->productReviews->getItems() as $item) {
            $node = ['@type' => 'Review'];
            if ($item['nickname'] !== '') {
                $node['author'] = ['@type' => 'Person', 'name' => $item['nickname']];
            }
            if ($item['title'] !== '') {
                $node['name'] = $item['title'];
            }
            if ($item['detail'] !== '') {
                $node['reviewBody'] = $item['detail'];
            }
            if ($item['created_at'] !== '') {
                $node['datePublished'] = $item['created_at'];
            }
            if ($item['percent'] > 0) {
                $node['reviewRating'] = [
                    '@type' => 'Rating',
                    'ratingValue' => (string)round($item['percent'] / self::PERCENT_PER_STAR, 1),
                    'bestRating' => (string)self::BEST_RATING,
                ];
            }
            $reviews[] = $node;
        }

        return $reviews;
    }
}
