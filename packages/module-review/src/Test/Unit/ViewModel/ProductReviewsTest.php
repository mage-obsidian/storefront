<?php
declare(strict_types=1);

namespace MageObsidian\Review\Test\Unit\ViewModel;

use Magento\Framework\Registry;
use Magento\Review\Model\ResourceModel\Review\Collection;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageObsidian\Review\ViewModel\ProductReviews;
use PHPUnit\Framework\TestCase;

/**
 * PDP review list ViewModel. Asserts it maps the approved-review collection (with
 * each review's average vote percentage) and derives the aggregate, and that it
 * degrades to empty off a product page. Needs Magento Review/Store types, so it
 * runs in a Magento root.
 */
class ProductReviewsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Collection::class)) {
            $this->markTestSkipped('Magento Review is not available in this runtime.');
        }
    }

    private function vote(int $percent): object
    {
        $vote = $this->getMockBuilder(\stdClass::class)->addMethods(['getPercent'])->getMock();
        $vote->method('getPercent')->willReturn($percent);

        return $vote;
    }

    private function review(string $title, array $votePercents): object
    {
        $review = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getTitle', 'getDetail', 'getNickname', 'getCreatedAt', 'getRatingVotes'])
            ->getMock();
        $review->method('getTitle')->willReturn($title);
        $review->method('getDetail')->willReturn('Body');
        $review->method('getNickname')->willReturn('Ada');
        $review->method('getCreatedAt')->willReturn('2026-06-21 00:00:00');
        $review->method('getRatingVotes')->willReturn(array_map(fn ($p) => $this->vote($p), $votePercents));

        return $review;
    }

    private function subject(?object $product, array $reviews): ProductReviews
    {
        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->with('current_product')->willReturn($product);

        $collection = $this->createMock(Collection::class);
        foreach (['addStoreFilter', 'addStatusFilter', 'addEntityFilter', 'setDateOrder', 'addRateVotes'] as $m) {
            $collection->method($m)->willReturnSelf();
        }
        $collection->method('getIterator')->willReturn(new \ArrayIterator($reviews));

        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new ProductReviews($registry, $factory, $storeManager);
    }

    private function product(): object
    {
        $product = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getId', 'getName', 'getProductUrl'])
            ->getMock();
        $product->method('getId')->willReturn(682);
        $product->method('getName')->willReturn('Helios Tank');
        $product->method('getProductUrl')->willReturn('https://shop.test/helios.html');

        return $product;
    }

    public function testMapsApprovedReviewsWithAverageVotePercent(): void
    {
        $vm = $this->subject($this->product(), [
            $this->review('Great', [100, 80]),
            $this->review('Good', [60, 60]),
        ]);

        $items = $vm->getItems();

        $this->assertCount(2, $items);
        $this->assertSame('Great', $items[0]['title']);
        $this->assertSame(90, $items[0]['percent']);
        $this->assertSame(60, $items[1]['percent']);
        $this->assertTrue($vm->hasReviews());
        $this->assertSame(2, $vm->getCount());
    }

    public function testDerivesAggregateAcrossReviews(): void
    {
        $vm = $this->subject($this->product(), [
            $this->review('A', [100]),
            $this->review('B', [50]),
        ]);

        $this->assertSame(75, $vm->getAveragePercent());
        $this->assertSame(3.8, $vm->getAverageStars());
        $this->assertSame('Helios Tank', $vm->getProductName());
    }

    public function testDegradesToEmptyOffAProductPage(): void
    {
        $vm = $this->subject(null, []);

        $this->assertSame([], $vm->getItems());
        $this->assertFalse($vm->hasReviews());
        $this->assertSame(0, $vm->getAveragePercent());
        $this->assertSame('', $vm->getProductName());
    }
}
