<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\Review\Test\Unit\Plugin\SchemaOrg;

use MageObsidian\ModernFrontend\Model\SchemaOrg\CurrentPageSchemaProvider;
use MageObsidian\Review\Plugin\SchemaOrg\AddProductRating;
use MageObsidian\Review\ViewModel\ProductReviews;
use PHPUnit\Framework\TestCase;

class AddProductRatingTest extends TestCase
{
    private CurrentPageSchemaProvider $subject;

    protected function setUp(): void
    {
        $this->subject = $this->createMock(CurrentPageSchemaProvider::class);
    }

    private function plugin(array $items, float $stars = 4.0): AddProductRating
    {
        $reviews = $this->createMock(ProductReviews::class);
        $reviews->method('hasReviews')->willReturn($items !== []);
        $reviews->method('getItems')->willReturn($items);
        $reviews->method('getCount')->willReturn(count($items));
        $reviews->method('getAverageStars')->willReturn($stars);

        return new AddProductRating($reviews);
    }

    private function review(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Great',
            'detail' => 'Solid bag.',
            'nickname' => 'Ada',
            'created_at' => '2026-01-02 10:00:00',
            'percent' => 80,
        ];
    }

    public function testLeavesTheNodesAloneWithoutReviews(): void
    {
        $nodes = [['@type' => 'Product', 'name' => 'X']];

        $this->assertSame($nodes, $this->plugin([])->afterGetCurrentPageNodes($this->subject, $nodes));
    }

    public function testAddsTheRatingToTheProductNode(): void
    {
        $nodes = [['@type' => 'WebSite'], ['@type' => 'Product', 'name' => 'X']];

        $result = $this->plugin([$this->review()], 4.5)->afterGetCurrentPageNodes($this->subject, $nodes);

        $this->assertSame(
            ['@type' => 'AggregateRating', 'ratingValue' => '4.5', 'reviewCount' => 1, 'bestRating' => '5'],
            $result[1]['aggregateRating']
        );
        $this->assertSame(['@type' => 'WebSite'], $result[0]);
    }

    public function testTurnsEachReviewIntoAReviewNode(): void
    {
        $nodes = [['@type' => 'Product', 'name' => 'X']];

        $result = $this->plugin([$this->review()])->afterGetCurrentPageNodes($this->subject, $nodes);

        $this->assertSame([[
            '@type' => 'Review',
            'author' => ['@type' => 'Person', 'name' => 'Ada'],
            'name' => 'Great',
            'reviewBody' => 'Solid bag.',
            'datePublished' => '2026-01-02 10:00:00',
            'reviewRating' => ['@type' => 'Rating', 'ratingValue' => '4', 'bestRating' => '5'],
        ]], $result[0]['review']);
    }

    public function testOmitsTheRatingOfAnUnratedReview(): void
    {
        $nodes = [['@type' => 'Product', 'name' => 'X']];

        $result = $this->plugin([$this->review(['percent' => 0])])
            ->afterGetCurrentPageNodes($this->subject, $nodes);

        $this->assertArrayNotHasKey('reviewRating', $result[0]['review'][0]);
    }

    public function testTouchesOnlyTheFirstProductNode(): void
    {
        $nodes = [['@type' => 'Product', 'name' => 'A'], ['@type' => 'Product', 'name' => 'B']];

        $result = $this->plugin([$this->review()])->afterGetCurrentPageNodes($this->subject, $nodes);

        $this->assertArrayHasKey('aggregateRating', $result[0]);
        $this->assertArrayNotHasKey('aggregateRating', $result[1]);
    }
}
