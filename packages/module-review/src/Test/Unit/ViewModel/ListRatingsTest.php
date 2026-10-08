<?php
/**
 * This file is part of the MageObsidian - Review project.
 *
 * SPDX-FileCopyrightText: 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: MIT
 */
declare(strict_types=1);

namespace MageObsidian\Review\Test\Unit\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Review\Service\RatingSummaryReader;
use MageObsidian\Review\ViewModel\ListRatings;
use PHPUnit\Framework\TestCase;

class ListRatingsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(ScopeConfigInterface::class)) {
            $this->markTestSkipped('Magento Framework is not available in this runtime.');
        }
    }

    private function product(int $id): object
    {
        return new class ($id) {
            public function __construct(private readonly int $id)
            {
            }

            public function getId(): int
            {
                return $this->id;
            }
        };
    }

    private function subject(bool $active, RatingSummaryReader $reader): ListRatings
    {
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('isSetFlag')->with('catalog/review/active')->willReturn($active);

        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new ListRatings($config, $storeManager, $reader);
    }

    public function testReadsTheSummariesOfEveryCardInOneQuery(): void
    {
        $reader = $this->createMock(RatingSummaryReader::class);
        $reader->expects($this->once())
            ->method('read')
            ->with([10, 11, 12], 1)
            ->willReturn([10 => ['percent' => 80, 'count' => 3], 11 => ['percent' => 60, 'count' => 1]]);

        $products = [$this->product(10), $this->product(11), $this->product(12)];
        $subject = $this->subject(true, $reader);

        $this->assertSame(['percent' => 80, 'count' => 3], $subject->getSummary($products[0], $products));
        $this->assertSame(['percent' => 60, 'count' => 1], $subject->getSummary($products[1], $products));
        $this->assertNull($subject->getSummary($products[2], $products));
    }

    public function testShowsNothingForAProductWithoutApprovedReviews(): void
    {
        $reader = $this->createMock(RatingSummaryReader::class);
        $reader->method('read')->willReturn([10 => ['percent' => 0, 'count' => 0]]);
        $products = [$this->product(10)];

        $this->assertNull($this->subject(true, $reader)->getSummary($products[0], $products));
    }

    public function testShowsNothingAndReadsNothingWhenReviewsAreDisabled(): void
    {
        $reader = $this->createMock(RatingSummaryReader::class);
        $reader->expects($this->never())->method('read');
        $products = [$this->product(10)];

        $this->assertNull($this->subject(false, $reader)->getSummary($products[0], $products));
    }
}
